<?php

namespace App\Console\Commands;

use App\Models\Attempt;
use App\Models\User;
use App\Support\CredentialSuggester;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Drives the real sign-in and quiz flow over HTTP with many users at once.
 *
 * Hitting /quiz with a plain benchmarking tool measures nothing useful: the
 * page is behind auth, so every request is just a redirect to /login. This
 * walks the actual Livewire round trips instead, and runs each phase in
 * lockstep so all users hit the server simultaneously — the burst that
 * happens when a room full of people starts at once.
 */
#[Signature('quiz:load-test {--url= : Base URL to hit} {--users=60} {--answers=10 : Questions to answer per user} {--keep : Leave the generated users behind}')]
#[Description('Simulate concurrent users signing in and taking a test, reporting per-phase latency.')]
class LoadTest extends Command
{
    private const USERNAME_PREFIX = 'loadtest.';

    private string $baseUrl;

    /** @var array<int, array<string, mixed>> */
    private array $users = [];

    public function handle(): int
    {
        $this->baseUrl = rtrim($this->option('url') ?: config('app.url'), '/');
        $count = (int) $this->option('users');
        $answers = (int) $this->option('answers');

        $this->line("Target: {$this->baseUrl}");
        $this->line("Users: {$count} · Answers each: {$answers}");
        $this->newLine();

        try {
            $this->createUsers($count);

            $this->phase('Sign-in page', fn () => $this->requests(fn (array $u) => [
                'url' => "{$this->baseUrl}/login",
            ], onDone: $this->captureLoginToken(...)));

            $this->phase('Sign in', fn () => $this->requests(fn (array $u) => [
                'url' => "{$this->baseUrl}/login",
                'post' => http_build_query([
                    '_token' => $u['token'],
                    'username' => $u['username'],
                    'password' => $u['password'],
                ]),
            ], onDone: $this->assertRedirect(...)));

            $this->phase('Open quiz', fn () => $this->requests(fn (array $u) => [
                'url' => "{$this->baseUrl}/quiz",
            ], onDone: $this->captureComponent(...)));

            $this->phase('Start test', fn () => $this->requests(
                fn (array $u) => $this->livewireRequest($u, calls: [['method' => 'start', 'params' => []]]),
                onDone: $this->captureLivewire(...),
            ));

            for ($i = 1; $i <= $answers; $i++) {
                $this->phase("Answer {$i}", fn () => $this->requests(
                    fn (array $u) => $this->livewireRequest($u, updates: ['selectedOptionId' => $u['optionId'] ?? null]),
                    onDone: $this->captureLivewire(...),
                ));

                $this->phase("Next {$i}", fn () => $this->requests(
                    fn (array $u) => $this->livewireRequest($u, calls: [['method' => 'next', 'params' => []]]),
                    onDone: $this->captureLivewire(...),
                ));
            }

            $this->phase('Finish test', fn () => $this->requests(
                fn (array $u) => $this->livewireRequest($u, calls: [['method' => 'finish', 'params' => []]]),
                onDone: $this->captureLivewire(...),
            ));

            $this->summarise();
        } finally {
            $this->cleanUp();
        }

        return self::SUCCESS;
    }

    private function createUsers(int $count): void
    {
        $this->components->task("Creating {$count} test users", function () use ($count) {
            $suggester = app(CredentialSuggester::class);

            for ($i = 1; $i <= $count; $i++) {
                $password = $suggester->password();
                $username = self::USERNAME_PREFIX.$i;

                User::updateOrCreate(
                    ['username' => $username],
                    [
                        'name' => "Load test {$i}",
                        'password' => Hash::make($password),
                        'plain_password' => $password,
                        'role' => 'user',
                    ],
                );

                $this->users[$i] = [
                    'username' => $username,
                    'password' => $password,
                    'jar' => tempnam(sys_get_temp_dir(), 'lt'),
                ];
            }
        });
    }

    /**
     * Run one request per user, all in flight at the same time.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $build
     * @param  callable(int, string, array<string, mixed>): void  $onDone
     * @return array{times: list<float>, failures: int}
     */
    private function requests(callable $build, callable $onDone): array
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($this->users as $index => $user) {
            if ($user['failed'] ?? false) {
                continue;
            }

            $spec = $build($user);
            $handle = curl_init();

            curl_setopt_array($handle, [
                CURLOPT_URL => $spec['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_COOKIEJAR => $user['jar'],
                CURLOPT_COOKIEFILE => $user['jar'],
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => $spec['headers'] ?? [],
            ]);

            if (isset($spec['post'])) {
                curl_setopt($handle, CURLOPT_POST, true);
                curl_setopt($handle, CURLOPT_POSTFIELDS, $spec['post']);
            }

            $handles[$index] = $handle;
            curl_multi_add_handle($multi, $handle);
        }

        $running = null;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi, 0.1);
        } while ($running > 0);

        $times = [];
        $failures = 0;

        foreach ($handles as $index => $handle) {
            $body = (string) curl_multi_getcontent($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $times[] = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000;

            if ($status >= 400 || $status === 0) {
                $failures++;
                $this->users[$index]['failed'] = true;
                $this->users[$index]['error'] = $status === 0 ? curl_error($handle) : "HTTP {$status}";
            } else {
                $onDone($index, $body, ['status' => $status]);
            }

            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }

        curl_multi_close($multi);

        return ['times' => $times, 'failures' => $failures];
    }

    /**
     * @param  list<array{method: string, params: array<int, mixed>}>  $calls
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function livewireRequest(array $user, array $calls = [], array $updates = []): array
    {
        return [
            'url' => $this->baseUrl.$user['livewireUri'],
            'post' => json_encode([
                'components' => [[
                    'snapshot' => $user['snapshot'],
                    'updates' => (object) $updates,
                    'calls' => $calls,
                ]],
            ]),
            'headers' => [
                'Content-Type: application/json',
                'X-Livewire: true',
                'X-CSRF-TOKEN: '.$user['csrf'],
            ],
        ];
    }

    private function captureLoginToken(int $index, string $body): void
    {
        if (preg_match('/name="_token" value="([^"]+)"/', $body, $m)) {
            $this->users[$index]['token'] = $m[1];

            return;
        }

        $this->users[$index]['failed'] = true;
        $this->users[$index]['error'] = 'No CSRF token on the sign-in page';
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function assertRedirect(int $index, string $body, array $info): void
    {
        // Fortify answers a good sign-in with a redirect; a 200 means the form
        // came back with errors.
        if ($info['status'] !== 302) {
            $this->users[$index]['failed'] = true;
            $this->users[$index]['error'] = 'Sign-in rejected';
        }
    }

    private function captureComponent(int $index, string $body): void
    {
        if (! preg_match('/wire:snapshot="([^"]+)"/', $body, $snapshot)
            || ! preg_match('/data-csrf="([^"]+)"/', $body, $csrf)
            || ! preg_match('#(/livewire-[a-z0-9]+)/livewire\.js#', $body, $uri)) {
            $this->users[$index]['failed'] = true;
            $this->users[$index]['error'] = 'Could not read the Livewire component';

            return;
        }

        $this->users[$index]['snapshot'] = html_entity_decode($snapshot[1], ENT_QUOTES);
        $this->users[$index]['csrf'] = $csrf[1];
        $this->users[$index]['livewireUri'] = $uri[1].'/update';
    }

    private function captureLivewire(int $index, string $body): void
    {
        $payload = json_decode($body, true);
        $component = $payload['components'][0] ?? null;

        if (! is_array($component) || ! isset($component['snapshot'])) {
            $this->users[$index]['failed'] = true;
            $this->users[$index]['error'] = 'Livewire returned no snapshot';

            return;
        }

        $this->users[$index]['snapshot'] = $component['snapshot'];

        // The next answer needs a valid option, which only the rendered HTML knows.
        $html = $component['effects']['html'] ?? '';

        if (preg_match('/wire:key="option-(\d+)"/', $html, $option)) {
            $this->users[$index]['optionId'] = (int) $option[1];
        }
    }

    /** @var array<string, array{times: list<float>, failures: int}> */
    private array $results = [];

    /**
     * @param  callable(): array{times: list<float>, failures: int}  $run
     */
    private function phase(string $name, callable $run): void
    {
        $started = microtime(true);
        $result = $run();
        $wall = (microtime(true) - $started) * 1000;

        $this->results[$name] = $result + ['wall' => $wall];

        $line = sprintf(
            '  %-14s %6.0f ms wall   p50 %5.0f   p95 %5.0f   max %5.0f',
            $name,
            $wall,
            $this->percentile($result['times'], 50),
            $this->percentile($result['times'], 95),
            $result['times'] ? max($result['times']) : 0,
        );

        $this->line($result['failures'] > 0
            ? "<fg=red>{$line}   {$result['failures']} FAILED</>"
            : $line);
    }

    /**
     * @param  list<float>  $times
     */
    private function percentile(array $times, int $percentile): float
    {
        if ($times === []) {
            return 0;
        }

        sort($times);
        $index = (int) ceil($percentile / 100 * count($times)) - 1;

        return $times[max(0, $index)];
    }

    private function summarise(): void
    {
        $this->newLine();

        $failed = collect($this->users)->where('failed', true);
        $slowest = collect($this->results)->max('wall');

        if ($failed->isEmpty()) {
            $this->components->info('All users completed the full flow.');
        } else {
            $this->components->error("{$failed->count()} of ".count($this->users).' users failed.');

            foreach ($failed->pluck('error')->countBy() as $error => $times) {
                $this->line("  {$times}× {$error}");
            }
        }

        $this->line(sprintf('  Slowest phase: %.0f ms for all users to complete.', $slowest));
    }

    private function cleanUp(): void
    {
        foreach ($this->users as $user) {
            @unlink($user['jar']);
        }

        if ($this->option('keep')) {
            return;
        }

        $this->components->task('Removing test users and their attempts', function () {
            $ids = User::query()->where('username', 'like', self::USERNAME_PREFIX.'%')->pluck('id');

            Attempt::query()->whereIn('user_id', $ids)->delete();
            User::query()->whereIn('id', $ids)->delete();
        });
    }
}
