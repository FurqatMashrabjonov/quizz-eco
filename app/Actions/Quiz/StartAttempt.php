<?php

namespace App\Actions\Quiz;

use App\Exceptions\MaxAttemptsReachedException;
use App\Models\Attempt;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuizSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StartAttempt
{
    public function __construct(private FinishAttempt $finishAttempt) {}

    /**
     * Resume the user's in-progress attempt, or create a new one.
     *
     * @throws MaxAttemptsReachedException
     */
    public function handle(User $user): Attempt
    {
        return Cache::lock("quiz-start-user-{$user->id}", 10)->block(5, function () use ($user) {
            return DB::transaction(function () use ($user) {
                $inProgress = Attempt::query()
                    ->where('user_id', $user->id)
                    ->whereNull('finished_at')
                    ->first();

                if ($inProgress) {
                    if (! $inProgress->isExpired()) {
                        return $inProgress;
                    }

                    $this->finishAttempt->handle($inProgress);
                }

                $settings = QuizSetting::current();

                $finishedCount = Attempt::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('finished_at')
                    ->count();

                if ($finishedCount >= $settings->max_attempts) {
                    throw new MaxAttemptsReachedException;
                }

                return Attempt::create([
                    'user_id' => $user->id,
                    'layout' => $this->buildLayout($settings->questions_per_attempt),
                    'started_at' => now(),
                    'expires_at' => now()->addMinutes($settings->duration_minutes),
                ]);
            });
        });
    }

    /**
     * @return list<array{q: int, o: list<int>}>
     */
    private function buildLayout(int $questionsPerAttempt): array
    {
        $questionIds = Question::query()
            ->where('is_active', true)
            ->inRandomOrder()
            ->limit($questionsPerAttempt)
            ->pluck('id');

        $optionIdsByQuestion = Option::query()
            ->whereIn('question_id', $questionIds)
            ->get(['id', 'question_id'])
            ->groupBy('question_id');

        return $questionIds
            ->map(fn (int $questionId) => [
                'q' => $questionId,
                'o' => $optionIdsByQuestion[$questionId]->pluck('id')->shuffle()->values()->all(),
            ])
            ->values()
            ->all();
    }
}
