<?php

use App\Models\Attempt;
use App\Models\Question;
use App\Models\QuizSetting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    QuizSetting::current()->update([
        'duration_minutes' => 30,
        'questions_per_attempt' => 3,
        'max_attempts' => 1,
    ]);

    Question::factory(5)->create();
});

test('a user with no attempt sees the start screen', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::quiz')
        ->assertSee('Boshlashga tayyormisiz?')
        ->assertSee('Testni boshlash');
});

test('starting the quiz creates an attempt and shows the first question', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::quiz')
        ->call('start')
        ->assertSet('currentIndex', 0)
        ->assertSee('0/3');

    expect(Attempt::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('selecting an option saves the answer and it survives a fresh mount', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::quiz')
        ->call('start');

    $attempt = Attempt::query()->where('user_id', $user->id)->firstOrFail();
    $optionId = $attempt->layout[0]['o'][0];

    $component->set('selectedOptionId', $optionId);

    expect($attempt->answers()->where('question_id', $attempt->layout[0]['q'])->value('option_id'))
        ->toBe($optionId);

    // A fresh mount (simulating a page refresh) resumes at the next unanswered question,
    // and going back to the first one shows the saved answer still selected.
    Livewire::actingAs($user)
        ->test('pages::quiz')
        ->assertSet('currentIndex', 1)
        ->call('previous')
        ->assertSet('selectedOptionId', $optionId);
});

test('finishing the quiz shows the score', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::quiz')
        ->call('start');

    $attempt = Attempt::query()->where('user_id', $user->id)->firstOrFail();

    foreach ($attempt->layout as $index => $entry) {
        $component->set('currentIndex', $index)
            ->set('selectedOptionId', $entry['o'][0]);
    }

    $component->call('finish')
        ->assertSee('Test yakunlandi');

    expect($attempt->refresh()->isFinished())->toBeTrue();
});

test('a user only ever sees their own attempt, never another user\'s', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Livewire::actingAs($userA)->test('pages::quiz')->call('start');

    Livewire::actingAs($userB)
        ->test('pages::quiz')
        ->assertSee('Boshlashga tayyormisiz?')
        ->assertDontSee('1 / 3');

    expect(Attempt::query()->where('user_id', $userB->id)->count())->toBe(0);
});

test('a user cannot start a new attempt once max attempts is reached', function () {
    $user = User::factory()->create();
    Attempt::factory()->for($user)->finished()->create();

    Livewire::actingAs($user)
        ->test('pages::quiz')
        ->call('start')
        ->assertSee('maksimal urinishlar');
});

test('the navigator lets a user jump to any question and change a previous answer', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::quiz')
        ->call('start');

    $attempt = Attempt::query()->where('user_id', $user->id)->firstOrFail();
    $firstOptionId = $attempt->layout[0]['o'][0];
    $otherOptionId = $attempt->layout[0]['o'][1];

    // Answer question 1, jump to question 3, then jump back to change question 1's answer.
    $component->set('selectedOptionId', $firstOptionId)
        ->call('goTo', 2)
        ->assertSet('currentIndex', 2)
        ->call('goTo', 0)
        ->assertSet('currentIndex', 0)
        ->assertSet('selectedOptionId', $firstOptionId)
        ->set('selectedOptionId', $otherOptionId);

    expect($attempt->answers()->where('question_id', $attempt->layout[0]['q'])->value('option_id'))
        ->toBe($otherOptionId);
});

test('revisiting the page after time runs out finishes the attempt automatically', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('pages::quiz')->call('start');

    $attempt = Attempt::query()->where('user_id', $user->id)->firstOrFail();
    $attempt->update(['expires_at' => now()->subMinute()]);

    Livewire::actingAs($user)
        ->test('pages::quiz')
        ->assertSee('Test yakunlandi');

    expect($attempt->refresh()->isFinished())->toBeTrue();
});
