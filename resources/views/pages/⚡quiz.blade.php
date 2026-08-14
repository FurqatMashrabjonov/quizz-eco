<?php

use App\Actions\Quiz\FinishAttempt;
use App\Actions\Quiz\StartAttempt;
use App\Actions\Quiz\SubmitAnswer;
use App\Exceptions\AttemptExpiredException;
use App\Exceptions\MaxAttemptsReachedException;
use App\Exceptions\NoQuestionsAvailableException;
use App\Models\Attempt;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuizSetting;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Test')] class extends Component {
    public ?Attempt $attempt = null;

    public int $currentIndex = 0;

    public ?int $selectedOptionId = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->attempt = Attempt::query()
            ->where('user_id', Auth::id())
            ->latest('id')
            ->first();

        // Older releases could create an attempt while the question bank was
        // empty. Such an attempt has nothing to render, so discard it and let
        // the user start again.
        if ($this->attempt && ! $this->attempt->isFinished() && $this->attempt->layout === []) {
            $this->attempt->delete();
            $this->attempt = null;
        }

        if ($this->attempt && ! $this->attempt->isFinished() && $this->attempt->isExpired()) {
            app(FinishAttempt::class)->handle($this->attempt);
            $this->attempt->refresh();
        }

        if ($this->attempt && ! $this->attempt->isFinished()) {
            $this->currentIndex = $this->firstUnansweredIndex();
            $this->loadSelectedOption();
        }
    }

    public function start(): void
    {
        // Closed either way: on failure the message is rendered behind the modal.
        Flux::modals()->close();

        try {
            $this->attempt = app(StartAttempt::class)->handle(Auth::user());
            $this->currentIndex = 0;
            $this->error = null;
            $this->loadSelectedOption();
        } catch (MaxAttemptsReachedException|NoQuestionsAvailableException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function updatedSelectedOptionId(?int $value): void
    {
        if ($value === null) {
            return;
        }

        try {
            app(SubmitAnswer::class)->handle($this->attempt, $this->entry()['q'], $value);
        } catch (AttemptExpiredException) {
            $this->attempt->refresh();
        }
    }

    public function goTo(int $index): void
    {
        $this->currentIndex = max(0, min($index, $this->lastIndex()));
        $this->loadSelectedOption();
    }

    public function next(): void
    {
        $this->currentIndex = min($this->currentIndex + 1, $this->lastIndex());
        $this->loadSelectedOption();
    }

    public function previous(): void
    {
        $this->currentIndex = max($this->currentIndex - 1, 0);
        $this->loadSelectedOption();
    }

    public function finish(): void
    {
        app(FinishAttempt::class)->handle($this->attempt);
        $this->attempt->refresh();
    }

    public function question(): Question
    {
        return Question::findOrFail($this->entry()['q']);
    }

    /**
     * @return Collection<int, Option>
     */
    public function options(): Collection
    {
        $options = Option::whereIn('id', $this->entry()['o'])->get()->keyBy('id');

        return collect($this->entry()['o'])->map(fn (int $id) => $options[$id]);
    }

    /**
     * @return list<int>
     */
    public function answeredQuestionIds(): array
    {
        return $this->attempt->answers()->pluck('question_id')->all();
    }

    public function lastIndex(): int
    {
        return count($this->attempt->layout) - 1;
    }

    /**
     * The start screen reads settings from several places; without memoizing,
     * each read is its own query.
     */
    #[Computed]
    public function settings(): QuizSetting
    {
        return QuizSetting::current();
    }

    /**
     * Render the configured duration as "2 soat 30 daqiqa" rather than "150 daqiqa".
     */
    #[Computed]
    public function durationLabel(): string
    {
        $minutes = $this->settings->duration_minutes;

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return match (true) {
            $hours === 0 => __(':count daqiqa', ['count' => $remainder]),
            $remainder === 0 => __(':count soat', ['count' => $hours]),
            default => __(':hours soat :minutes daqiqa', ['hours' => $hours, 'minutes' => $remainder]),
        };
    }

    /**
     * Per-question breakdown for the finished-attempt review: what the user
     * picked versus the correct option. Two queries total, not one per question.
     *
     * @return Collection<int, array{question: string, selected: ?string, correct: ?string, explanation: ?string, isCorrect: bool}>
     */
    #[Computed]
    public function reviewRows(): Collection
    {
        $questions = Question::query()
            ->with('options')
            ->whereIn('id', $this->attempt->questionIds())
            ->get()
            ->keyBy('id');

        $answers = $this->attempt->answers()->with('option')->get()->keyBy('question_id');

        return collect($this->attempt->layout)
            // A question answered at the time can be deleted by an admin afterwards;
            // the layout still references its id, so skip it rather than crash.
            ->filter(fn (array $entry) => $questions->has($entry['q']))
            ->map(function (array $entry) use ($questions, $answers) {
                $question = $questions[$entry['q']];
                $selectedOption = $answers->get($entry['q'])?->option;

                return [
                    'question' => $question->body,
                    'selected' => $selectedOption?->body,
                    'correct' => $question->options->firstWhere('is_correct', true)?->body,
                    'explanation' => $question->explanation,
                    'isCorrect' => $selectedOption?->is_correct ?? false,
                ];
            })
            ->values();
    }

    #[Computed]
    public function questionCount(): int
    {
        return min(
            $this->settings->questions_per_attempt,
            Question::query()->where('is_active', true)->count(),
        );
    }

    #[Computed]
    public function attemptsRemaining(): int
    {
        $finishedCount = Attempt::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('finished_at')
            ->count();

        return max(0, $this->settings->max_attempts - $finishedCount);
    }

    /**
     * @return array{q: int, o: list<int>}
     */
    private function entry(): array
    {
        return $this->attempt->layout[$this->currentIndex];
    }

    private function loadSelectedOption(): void
    {
        $this->selectedOptionId = $this->attempt->answers()
            ->where('question_id', $this->entry()['q'])
            ->value('option_id');
    }

    private function firstUnansweredIndex(): int
    {
        $answeredIds = $this->attempt->answers()->pluck('question_id')->all();

        foreach ($this->attempt->layout as $index => $entry) {
            if (! in_array($entry['q'], $answeredIds, true)) {
                return $index;
            }
        }

        return 0;
    }
}; ?>

<div class="mx-auto w-full">
    @if (! $attempt || $attempt->isFinished())
        <flux:card class="mx-auto max-w-2xl space-y-6 p-6 sm:p-8">
            @if ($attempt?->isFinished())
                <div class="space-y-2 text-center">
                    <flux:heading size="xl">{{ __('Test yakunlandi') }}</flux:heading>
                    <flux:text class="text-4xl font-bold sm:text-5xl">{{ $attempt->score }} / {{ $attempt->total }}</flux:text>
                    <flux:text class="text-lg sm:text-xl">
                        {{ $attempt->total > 0 ? round($attempt->score / $attempt->total * 100) : 0 }}%
                    </flux:text>
                </div>
            @else
                <div class="space-y-2 text-center">
                    <flux:heading size="xl">
                        {{ __(':count ta savol · :duration', ['count' => $this->questionCount, 'duration' => $this->durationLabel]) }}
                    </flux:heading>
                </div>
            @endif

            @if ($error)
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$error" />
            @endif

            @if ($this->attemptsRemaining > 0)
                <flux:modal.trigger name="start-quiz">
                    <flux:button variant="primary" class="w-full py-3 text-base sm:py-4 sm:text-lg">
                        {{ $attempt?->isFinished() ? __('Qayta boshlash') : __('Testni boshlash') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </flux:card>

        @if ($attempt?->isFinished())
            <flux:card class="mx-auto mt-4 max-w-3xl space-y-3 p-6 sm:p-8">
                <flux:heading size="lg">{{ __("Savollar bo'yicha natija") }}</flux:heading>

                <div class="space-y-3">
                    @foreach ($this->reviewRows as $review)
                        {{-- The colour lives on a thin start-edge accent, so a page of
                             wrong answers stays readable instead of a wall of red. --}}
                        <div
                            wire:key="review-{{ $loop->index }}"
                            @class([
                                'overflow-hidden rounded-lg border border-s-2 border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900',
                                'border-s-green-500 dark:border-s-green-500' => $review['isCorrect'],
                                'border-s-red-500 dark:border-s-red-500' => ! $review['isCorrect'],
                            ])
                        >
                            <div class="flex items-start gap-3 p-4">
                                <flux:text class="shrink-0 font-mono text-sm text-zinc-400 dark:text-zinc-500">
                                    {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </flux:text>

                                <div class="min-w-0 flex-1 space-y-3">
                                    <flux:heading class="text-sm leading-relaxed sm:text-base">
                                        {{ $review['question'] }}
                                    </flux:heading>

                                    <div class="space-y-2">
                                        <div class="flex items-start gap-2">
                                            @if ($review['isCorrect'])
                                                <flux:icon.check-circle variant="mini" class="mt-0.5 shrink-0 text-green-600 dark:text-green-500" />
                                            @else
                                                <flux:icon.x-circle variant="mini" class="mt-0.5 shrink-0 text-red-500 dark:text-red-400" />
                                            @endif

                                            <div class="min-w-0">
                                                <flux:text class="text-xs uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                                    {{ __('Sizning javobingiz') }}
                                                </flux:text>
                                                <flux:text class="text-sm text-zinc-800 sm:text-base dark:text-zinc-200">
                                                    {{ $review['selected'] ?? __('Javob berilmagan') }}
                                                </flux:text>
                                            </div>
                                        </div>

                                        @unless ($review['isCorrect'])
                                            <div class="flex items-start gap-2">
                                                <flux:icon.check-circle variant="mini" class="mt-0.5 shrink-0 text-green-600 dark:text-green-500" />

                                                <div class="min-w-0">
                                                    <flux:text class="text-xs uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                                                        {{ __("To'g'ri javob") }}
                                                    </flux:text>
                                                    <flux:text class="text-sm text-zinc-800 sm:text-base dark:text-zinc-200">
                                                        {{ $review['correct'] }}
                                                    </flux:text>
                                                </div>
                                            </div>
                                        @endunless
                                    </div>

                                    @if (! $review['isCorrect'] && $review['explanation'])
                                        <div class="rounded-md bg-zinc-50 px-3 py-2 dark:bg-zinc-800">
                                            <flux:text class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                                {{ $review['explanation'] }}
                                            </flux:text>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif

        <flux:modal name="start-quiz" class="w-full max-w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Testni boshlashdan oldin') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Quyidagilarni diqqat bilan o\'qing.') }}</flux:text>
                </div>

                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    <div class="flex items-center justify-between p-3">
                        <flux:text>{{ __('Savollar soni') }}</flux:text>
                        <flux:text class="font-semibold">{{ $this->questionCount }} {{ __('ta') }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between p-3">
                        <flux:text>{{ __('Berilgan vaqt') }}</flux:text>
                        <flux:text class="font-semibold">{{ $this->durationLabel }}</flux:text>
                    </div>
                </div>

                <ul class="space-y-2">
                    @foreach ([
                        __('Vaqt boshlangandan keyin to\'xtamaydi — sahifani yopsangiz ham sanoq davom etadi.'),
                        __('Test tugaguncha sahifadan chiqmang va brauzerni yopmang.'),
                        __('Har bir javob avtomatik saqlanadi, savollar orasida erkin harakatlanishingiz mumkin.'),
                        __('Test yakunlangach javoblarni o\'zgartirib bo\'lmaydi.'),
                    ] as $rule)
                        <li class="flex gap-2">
                            <flux:icon.exclamation-triangle variant="mini" class="mt-0.5 shrink-0 text-amber-500" />
                            <flux:text class="text-sm">{{ $rule }}</flux:text>
                        </li>
                    @endforeach
                </ul>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Bekor qilish') }}</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="start" variant="primary">
                        {{ __('Boshlash') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @else
        <div
            class="mx-auto max-w-5xl space-y-4"
            x-data="{
                expiresAt: {{ $attempt->expires_at->timestamp * 1000 }},
                remaining: 0,
                interval: null,
                tick() {
                    this.remaining = Math.max(0, Math.round((this.expiresAt - Date.now()) / 1000))
                    if (this.remaining <= 0) {
                        clearInterval(this.interval)
                        $wire.finish()
                    }
                },
                format() {
                    let h = Math.floor(this.remaining / 3600)
                    let m = Math.floor((this.remaining % 3600) / 60).toString().padStart(2, '0')
                    let s = (this.remaining % 60).toString().padStart(2, '0')
                    return h > 0 ? (h + ':' + m + ':' + s) : (m + ':' + s)
                },
            }"
            x-init="tick(); interval = setInterval(() => tick(), 1000)"
        >
            <div class="flex items-center justify-end">
                <span
                    class="shrink-0 rounded-full border px-2.5 py-1 font-mono text-sm font-semibold tabular-nums sm:px-3 sm:py-1.5 sm:text-base"
                    x-bind:class="remaining <= 60
                        ? 'border-red-300 bg-red-50 text-red-600 dark:border-red-800 dark:bg-red-950 dark:text-red-400'
                        : 'border-neutral-200 bg-neutral-50 text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'"
                    x-text="format()"
                ></span>
            </div>

            <!-- Question navigator -->
            <flux:card class="space-y-3 p-4 sm:p-6">
                @php $answeredIds = $this->answeredQuestionIds(); @endphp

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <flux:heading size="sm">{{ __('Savollar') }}</flux:heading>
                        <flux:text class="text-sm font-semibold sm:text-base">{{ count($answeredIds) }}/{{ $this->lastIndex() + 1 }}</flux:text>
                    </div>

                    <flux:button
                        wire:click="finish"
                        wire:confirm="{{ __('Rostdan ham testni yakunlamoqchimisiz? Bu amalni ortga qaytarib bo\'lmaydi.') }}"
                        variant="danger"
                        class="px-4 py-2 text-sm sm:px-6 sm:py-2.5 sm:text-base"
                    >
                        {{ __('Yakunlash') }}
                    </flux:button>
                </div>

                <div class="flex flex-wrap gap-1.5 sm:gap-2">
                    @foreach ($attempt->layout as $index => $entry)
                        <button
                            type="button"
                            wire:click="goTo({{ $index }})"
                            wire:key="nav-{{ $index }}"
                            @class([
                                'flex size-7 shrink-0 items-center justify-center rounded-md border text-xs font-semibold sm:size-9 sm:text-sm',
                                'border-accent bg-accent text-accent-foreground' => $index === $currentIndex,
                                'border-green-300 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-400' => $index !== $currentIndex && in_array($entry['q'], $answeredIds, true),
                                'border-neutral-200 bg-white text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300' => $index !== $currentIndex && ! in_array($entry['q'], $answeredIds, true),
                            ])
                        >
                            {{ $index + 1 }}
                        </button>
                    @endforeach
                </div>
            </flux:card>

            <!-- Current question -->
            <flux:card class="space-y-6 p-4 sm:p-6">
                <flux:heading size="lg" class="text-base sm:text-xl">{{ $this->question()->body }}</flux:heading>

                <flux:radio.group wire:model.live="selectedOptionId" variant="cards" class="flex-col gap-3">
                    @foreach ($this->options() as $option)
                        <flux:radio
                            wire:key="option-{{ $option->id }}"
                            value="{{ $option->id }}"
                            :label="$option->body"
                            class="min-h-14 text-sm sm:min-h-16 sm:text-lg"
                        />
                    @endforeach
                </flux:radio.group>
            </flux:card>

            <div class="flex items-center justify-between gap-4">
                <flux:button wire:click="previous" :disabled="$currentIndex === 0" variant="primary" class="px-4 py-2.5 text-sm sm:px-6 sm:py-4 sm:text-lg">
                    {{ __('Orqaga') }}
                </flux:button>

                <flux:button wire:click="next" :disabled="$currentIndex === $this->lastIndex()" variant="primary" class="px-4 py-2.5 text-sm sm:px-8 sm:py-4 sm:text-lg">
                    {{ __('Keyingi') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
