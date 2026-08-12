<?php

use App\Actions\Quiz\SubmitAnswer;
use App\Exceptions\AttemptExpiredException;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\User;

function makeAttemptWithQuestions(int $count = 3): Attempt
{
    $questions = Question::factory($count)->create();

    $layout = $questions->map(fn (Question $question) => [
        'q' => $question->id,
        'o' => $question->options->pluck('id')->shuffle()->values()->all(),
    ])->values()->all();

    return Attempt::factory()->for(User::factory())->create([
        'layout' => $layout,
    ]);
}

test('it records the chosen option as an answer', function () {
    $attempt = makeAttemptWithQuestions();
    $entry = $attempt->layout[0];

    app(SubmitAnswer::class)->handle($attempt, $entry['q'], $entry['o'][0]);

    expect($attempt->answers()->count())->toBe(1)
        ->and($attempt->answers()->first())
        ->question_id->toBe($entry['q'])
        ->option_id->toBe($entry['o'][0]);
});

test('answering the same question again updates the previous answer instead of duplicating it', function () {
    $attempt = makeAttemptWithQuestions();
    $entry = $attempt->layout[0];

    app(SubmitAnswer::class)->handle($attempt, $entry['q'], $entry['o'][0]);
    app(SubmitAnswer::class)->handle($attempt, $entry['q'], $entry['o'][1]);

    expect($attempt->answers()->count())->toBe(1)
        ->and($attempt->answers()->first()->option_id)->toBe($entry['o'][1]);
});

test('it rejects an option that does not belong to the question', function () {
    $attempt = makeAttemptWithQuestions(2);
    $questionOne = $attempt->layout[0]['q'];
    $optionFromQuestionTwo = $attempt->layout[1]['o'][0];

    app(SubmitAnswer::class)->handle($attempt, $questionOne, $optionFromQuestionTwo);
})->throws(InvalidArgumentException::class);

test('it rejects answers once the attempt has expired', function () {
    $attempt = makeAttemptWithQuestions();
    $attempt->update(['expires_at' => now()->subMinute()]);
    $entry = $attempt->layout[0];

    app(SubmitAnswer::class)->handle($attempt, $entry['q'], $entry['o'][0]);
})->throws(AttemptExpiredException::class);

test('expiring an attempt via an answer submission finishes it and scores what was answered', function () {
    $attempt = makeAttemptWithQuestions(2);
    $correctOptionId = Question::find($attempt->layout[0]['q'])->options()->where('is_correct', true)->value('id');

    app(SubmitAnswer::class)->handle($attempt, $attempt->layout[0]['q'], $correctOptionId);

    $attempt->update(['expires_at' => now()->subMinute()]);
    $entry = $attempt->layout[1];

    try {
        app(SubmitAnswer::class)->handle($attempt, $entry['q'], $entry['o'][0]);
    } catch (AttemptExpiredException) {
        // expected
    }

    $attempt->refresh();

    expect($attempt->isFinished())->toBeTrue()
        ->and($attempt->score)->toBe(1)
        ->and($attempt->answers()->count())->toBe(1);
});

test('it rejects answers once the attempt is already finished', function () {
    $attempt = makeAttemptWithQuestions();
    $attempt->update(['finished_at' => now(), 'score' => 0, 'total' => 3]);
    $entry = $attempt->layout[0];

    app(SubmitAnswer::class)->handle($attempt, $entry['q'], $entry['o'][0]);
})->throws(AttemptExpiredException::class);
