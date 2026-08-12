<?php

use App\Filament\Resources\Attempts\Pages\ManageAttempts;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('attempts list shows correct, wrong, total and score percentage', function () {
    $user = User::factory()->create(['name' => 'Test Taker']);
    Attempt::factory()->for($user)->finished(score: 3, total: 4)->create();

    Livewire::test(ManageAttempts::class)
        ->assertSee('Test Taker')
        ->assertSee('75%');
});

test('an admin can delete an attempt, freeing the user to retake the test', function () {
    $user = User::factory()->create();
    $attempt = Attempt::factory()->for($user)->finished(score: 2, total: 4)->create();

    Livewire::test(ManageAttempts::class)
        ->callTableAction('delete', $attempt)
        ->assertHasNoTableActionErrors();

    expect(Attempt::query()->whereKey($attempt->id)->exists())->toBeFalse();
});

test('deleting an attempt removes its answers too', function () {
    $question = Question::factory()->create();
    $attempt = Attempt::factory()->for(User::factory())->create([
        'layout' => [['q' => $question->id, 'o' => $question->options->pluck('id')->all()]],
    ]);
    $attempt->answers()->create([
        'question_id' => $question->id,
        'option_id' => $question->options->first()->id,
    ]);

    Livewire::test(ManageAttempts::class)->callTableAction('delete', $attempt);

    expect(Answer::query()->where('attempt_id', $attempt->id)->count())->toBe(0);
});

test('the export csv action downloads a file', function () {
    Attempt::factory()->for(User::factory())->finished(score: 2, total: 4)->create();

    Livewire::test(ManageAttempts::class)
        ->callAction('exportCsv')
        ->assertFileDownloaded('urinishlar.csv');
});
