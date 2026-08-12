<?php

use App\Models\Option;
use App\Models\Question;

test('it creates the requested number of questions with four options each', function () {
    $this->artisan('quiz:seed-questions', ['count' => 5])->assertSuccessful();

    expect(Question::query()->count())->toBe(5)
        ->and(Option::query()->count())->toBe(20);

    Question::with('options')->get()->each(function (Question $question) {
        expect($question->options)->toHaveCount(4)
            ->and($question->options->where('is_correct', true))->toHaveCount(1);
    });
});

test('it defaults to 200 questions', function () {
    $this->artisan('quiz:seed-questions')->assertSuccessful();

    expect(Question::query()->count())->toBe(200);
});

test('it appends to existing questions by default', function () {
    Question::factory(3)->create();

    $this->artisan('quiz:seed-questions', ['count' => 2])->assertSuccessful();

    expect(Question::query()->count())->toBe(5);
});

test('the fresh option replaces existing questions once confirmed', function () {
    Question::factory(3)->create();

    $this->artisan('quiz:seed-questions', ['count' => 2, '--fresh' => true])
        ->expectsConfirmation('Davom etilsinmi?', 'yes')
        ->assertSuccessful();

    expect(Question::query()->count())->toBe(2)
        ->and(Option::query()->count())->toBe(8);
});

test('declining the confirmation leaves existing questions untouched', function () {
    Question::factory(3)->create();

    $this->artisan('quiz:seed-questions', ['count' => 2, '--fresh' => true])
        ->expectsConfirmation('Davom etilsinmi?', 'no')
        ->assertFailed();

    expect(Question::query()->count())->toBe(3);
});

test('it rejects a count below one', function () {
    $this->artisan('quiz:seed-questions', ['count' => 0])->assertFailed();

    expect(Question::query()->count())->toBe(0);
});

test('generated questions are active so they can be drawn into a test', function () {
    $this->artisan('quiz:seed-questions', ['count' => 3])->assertSuccessful();

    expect(Question::query()->where('is_active', true)->count())->toBe(3);
});
