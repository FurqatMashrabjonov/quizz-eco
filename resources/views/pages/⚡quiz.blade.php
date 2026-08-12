<?php

use App\Actions\Quiz\FinishAttempt;
use App\Actions\Quiz\StartAttempt;
use App\Actions\Quiz\SubmitAnswer;
use App\Exceptions\AttemptExpiredException;
use App\Exceptions\MaxAttemptsReachedException;
use App\Models\Attempt;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuizSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
        try {
            $this->attempt = app(StartAttempt::class)->handle(Auth::user());
            $this->currentIndex = 0;
            $this->error = null;
            $this->loadSelectedOption();
        } catch (MaxAttemptsReachedException $e) {
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

    public function attemptsRemaining(): int
    {
        $finishedCount = Attempt::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('finished_at')
            ->count();

        return max(0, QuizSetting::current()->max_attempts - $finishedCount);
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
                    <flux:heading size="xl">{{ __('Boshlashga tayyormisiz?') }}</flux:heading>
                    <flux:text class="text-sm sm:text-base">
                        {{ __(':count ta savol · :minutes daqiqa', ['count' => QuizSetting::current()->questions_per_attempt, 'minutes' => QuizSetting::current()->duration_minutes]) }}
                    </flux:text>
                </div>
            @endif

            @if ($error)
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$error" />
            @endif

            @if ($this->attemptsRemaining() > 0)
                <flux:button wire:click="start" variant="primary" class="w-full py-3 text-base sm:py-4 sm:text-lg">
                    {{ $attempt?->isFinished() ? __('Qayta boshlash') : __('Testni boshlash') }}
                </flux:button>
                <flux:text class="text-center text-sm sm:text-base">{{ __(':count ta urinish qoldi', ['count' => $this->attemptsRemaining()]) }}</flux:text>
            @else
                <flux:callout variant="secondary" icon="check-circle" :heading="__('Urinishlar tugadi.')" />
            @endif
        </flux:card>
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
