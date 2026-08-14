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

test('the start screen warns about the rules before the test begins', function () {
    QuizSetting::current()->update(['duration_minutes' => 120, 'max_attempts' => 2]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::quiz')
        ->assertSee('Testni boshlashdan oldin')
        ->assertSee('2 soat')
        ->assertSee('Savollar soni')
        ->assertDontSee('Qolgan urinishlar')
        ->assertSee('sanoq davom etadi', escape: false);
});

test('the duration is worded in hours and minutes', function (int $minutes, string $expected) {
    QuizSetting::current()->update(['duration_minutes' => $minutes]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::quiz')
        ->assertSee($expected);
})->with([
    'under an hour' => [45, '45 daqiqa'],
    'whole hours' => [120, '2 soat'],
    'hours and minutes' => [90, '1 soat 30 daqiqa'],
]);

test('the warning shows how many questions will actually be drawn', function () {
    Question::query()->delete();
    Question::factory(4)->create();
    QuizSetting::current()->update(['questions_per_attempt' => 60]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::quiz')
        ->assertSee('4');
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

test('starting with an empty question bank shows a message instead of crashing', function () {
    Question::query()->delete();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::quiz')
        ->call('start')
        ->assertSuccessful()
        ->assertSee('savollar mavjud emas');

    expect(Attempt::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('an attempt left over with an empty layout is discarded rather than rendered', function () {
    // Older releases could persist this; the page must recover, not 500.
    $user = User::factory()->create();
    $broken = Attempt::factory()->for($user)->create(['layout' => []]);

    Livewire::actingAs($user)
        ->test('pages::quiz')
        ->assertSuccessful()
        ->assertSee('Boshlashga tayyormisiz?');

    expect(Attempt::query()->whereKey($broken->id)->exists())->toBeFalse();
});

test('a finished attempt with an empty layout is left alone', function () {
    $user = User::factory()->create();
    $finished = Attempt::factory()->for($user)->finished(score: 0, total: 0)->create(['layout' => []]);

    Livewire::actingAs($user)
        ->test('pages::quiz')
        ->assertSuccessful()
        ->assertSee('Test yakunlandi');

    expect(Attempt::query()->whereKey($finished->id)->exists())->toBeTrue();
});

test('finishing the quiz shows a per-question review of right and wrong answers', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::quiz')
        ->call('start');

    $attempt = Attempt::query()->where('user_id', $user->id)->firstOrFail();

    $firstQuestion = Question::findOrFail($attempt->layout[0]['q']);

    // Leave the first question unanswered and answer every other question right.
    foreach ($attempt->layout as $index => $entry) {
        if ($index === 0) {
            continue;
        }

        $correctId = Question::findOrFail($entry['q'])->options->firstWhere('is_correct', true)->id;
        $component->set('currentIndex', $index)->set('selectedOptionId', $correctId);
    }

    $component->call('finish')
        ->assertSee($firstQuestion->body)
        ->assertSee('Javob berilmagan')
        ->assertSee("To'g'ri javob:");

    expect($attempt->refresh()->score)->toBe(2);
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
