<?php

namespace App\Actions\Quiz;

use App\Exceptions\AttemptExpiredException;
use App\Models\Attempt;
use InvalidArgumentException;

class SubmitAnswer
{
    public function __construct(private FinishAttempt $finishAttempt) {}

    /**
     * @throws AttemptExpiredException
     */
    public function handle(Attempt $attempt, int $questionId, int $optionId): void
    {
        if ($attempt->isFinished()) {
            throw new AttemptExpiredException;
        }

        if ($attempt->isExpired()) {
            $this->finishAttempt->handle($attempt);

            throw new AttemptExpiredException;
        }

        $questionLayout = collect($attempt->layout)->firstWhere('q', $questionId);

        if (! $questionLayout || ! in_array($optionId, $questionLayout['o'], true)) {
            throw new InvalidArgumentException('This option does not belong to the given question.');
        }

        $attempt->answers()->updateOrCreate(
            ['question_id' => $questionId],
            ['option_id' => $optionId],
        );
    }
}
