<?php

use App\Actions\Quiz\StartAttempt;
use App\Exceptions\MaxAttemptsReachedException;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\QuizSetting;
use App\Models\User;

beforeEach(function () {
    QuizSetting::current()->update([
        'duration_minutes' => 30,
        'questions_per_attempt' => 5,
        'max_attempts' => 1,
    ]);

    Question::factory(10)->create();
});

test('it creates an attempt with a shuffled layout', function () {
    $user = User::factory()->create();

    $attempt = app(StartAttempt::class)->handle($user);

    expect($attempt->user_id)->toBe($user->id)
        ->and($attempt->layout)->toHaveCount(5)
        ->and($attempt->finished_at)->toBeNull()
        ->and((int) $attempt->started_at->diffInMinutes($attempt->expires_at))->toBe(30);

    foreach ($attempt->layout as $entry) {
        expect($entry['o'])->toHaveCount(4);
    }
});

test('two attempts get different question and option orders', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    QuizSetting::current()->update(['questions_per_attempt' => 10]);

    $attemptA = app(StartAttempt::class)->handle($userA);
    $attemptB = app(StartAttempt::class)->handle($userB);

    expect($attemptA->layout)->not->toBe($attemptB->layout);
});

test('resuming an in-progress attempt returns the same attempt without changing its layout', function () {
    $user = User::factory()->create();

    $first = app(StartAttempt::class)->handle($user);
    $originalLayout = $first->layout;

    $second = app(StartAttempt::class)->handle($user);

    expect($second->id)->toBe($first->id)
        ->and($second->layout)->toBe($originalLayout)
        ->and(Attempt::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('it throws once the user has reached the max attempts', function () {
    $user = User::factory()->create();

    Attempt::factory()->for($user)->finished()->create();

    app(StartAttempt::class)->handle($user);
})->throws(MaxAttemptsReachedException::class);

test('a new attempt can start after a previous one finished, within the attempt limit', function () {
    QuizSetting::current()->update(['max_attempts' => 2]);

    $user = User::factory()->create();
    Attempt::factory()->for($user)->finished()->create();

    $attempt = app(StartAttempt::class)->handle($user);

    expect($attempt->exists)->toBeTrue()
        ->and(Attempt::query()->where('user_id', $user->id)->count())->toBe(2);
});
