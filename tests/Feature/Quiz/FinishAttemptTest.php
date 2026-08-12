<?php

use App\Actions\Quiz\FinishAttempt;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\User;

test('it scores the attempt by counting correct answers', function () {
    $questions = Question::factory(4)->create();

    $layout = $questions->map(fn (Question $question) => [
        'q' => $question->id,
        'o' => $question->options->pluck('id')->values()->all(),
    ])->values()->all();

    $attempt = Attempt::factory()->for(User::factory())->create(['layout' => $layout]);

    // Answer the first two correctly, the third incorrectly, and leave the fourth unanswered.
    foreach ($questions->take(2) as $question) {
        $correctOptionId = $question->options()->where('is_correct', true)->value('id');
        $attempt->answers()->create(['question_id' => $question->id, 'option_id' => $correctOptionId]);
    }

    $wrongOptionId = $questions[2]->options()->where('is_correct', false)->value('id');
    $attempt->answers()->create(['question_id' => $questions[2]->id, 'option_id' => $wrongOptionId]);

    $result = app(FinishAttempt::class)->handle($attempt);

    expect($result->score)->toBe(2)
        ->and($result->total)->toBe(4)
        ->and($result->finished_at)->not->toBeNull();
});

test('finishing an already finished attempt is a no-op', function () {
    $attempt = Attempt::factory()->for(User::factory())->finished(score: 3, total: 5)->create();

    $result = app(FinishAttempt::class)->handle($attempt);

    expect($result->score)->toBe(3)
        ->and($result->total)->toBe(5);
});
