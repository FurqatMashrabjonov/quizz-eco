<?php

namespace App\Actions\Quiz;

use App\Models\Attempt;

class FinishAttempt
{
    public function handle(Attempt $attempt): Attempt
    {
        if ($attempt->isFinished()) {
            return $attempt;
        }

        $score = $attempt->answers()
            ->join('options', 'answers.option_id', '=', 'options.id')
            ->where('options.is_correct', true)
            ->count();

        $attempt->update([
            'finished_at' => now(),
            'score' => $score,
            'total' => count($attempt->layout),
        ]);

        return $attempt;
    }
}
