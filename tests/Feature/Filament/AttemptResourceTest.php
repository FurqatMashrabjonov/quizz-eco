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

test('attempts can be sorted by percentage even though it is not a column', function () {
    $low = Attempt::factory()->for(User::factory())->finished(score: 2, total: 10)->create();   // 20%
    $high = Attempt::factory()->for(User::factory())->finished(score: 9, total: 10)->create();  // 90%
    $mid = Attempt::factory()->for(User::factory())->finished(score: 5, total: 10)->create();   // 50%

    Livewire::test(ManageAttempts::class)
        ->sortTable('percentage')
        ->assertCanSeeTableRecords([$low, $mid, $high], inOrder: true)
        ->sortTable('percentage', 'desc')
        ->assertCanSeeTableRecords([$high, $mid, $low], inOrder: true);
});

test('sorting by percentage does not divide by zero on unfinished attempts', function () {
    $finished = Attempt::factory()->for(User::factory())->finished(score: 5, total: 10)->create();
    $running = Attempt::factory()->for(User::factory())->create(['total' => null]);

    Livewire::test(ManageAttempts::class)
        ->sortTable('percentage')
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$finished, $running]);
});

test('attempts can be filtered to finished or in-progress', function () {
    $finished = Attempt::factory()->for(User::factory())->finished(score: 1, total: 2)->create();
    $running = Attempt::factory()->for(User::factory())->create();

    Livewire::test(ManageAttempts::class)
        ->filterTable('finished_at', true)
        ->assertCanSeeTableRecords([$finished])
        ->assertCanNotSeeTableRecords([$running])
        ->filterTable('finished_at', false)
        ->assertCanSeeTableRecords([$running])
        ->assertCanNotSeeTableRecords([$finished]);
});

test('attempts can be filtered by a percentage range', function () {
    $low = Attempt::factory()->for(User::factory())->finished(score: 2, total: 10)->create();   // 20%
    $mid = Attempt::factory()->for(User::factory())->finished(score: 6, total: 10)->create();   // 60%
    $high = Attempt::factory()->for(User::factory())->finished(score: 9, total: 10)->create();  // 90%

    Livewire::test(ManageAttempts::class)
        ->filterTable('percentage', ['from' => 50, 'until' => 80])
        ->assertCanSeeTableRecords([$mid])
        ->assertCanNotSeeTableRecords([$low, $high]);
});

test('attempts can be filtered by date range', function () {
    $old = Attempt::factory()->for(User::factory())->create(['started_at' => now()->subDays(10)]);
    $recent = Attempt::factory()->for(User::factory())->create(['started_at' => now()]);

    Livewire::test(ManageAttempts::class)
        ->filterTable('started_at', ['from' => now()->subDay()->toDateString(), 'until' => null])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

test('attempts can be filtered by user', function () {
    $alisher = User::factory()->create(['name' => 'Alisher']);
    $dilnoza = User::factory()->create(['name' => 'Dilnoza']);
    $a = Attempt::factory()->for($alisher)->create();
    $d = Attempt::factory()->for($dilnoza)->create();

    Livewire::test(ManageAttempts::class)
        ->filterTable('user_id', $alisher->id)
        ->assertCanSeeTableRecords([$a])
        ->assertCanNotSeeTableRecords([$d]);
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

test('the export action downloads an xlsx file', function () {
    Attempt::factory()->for(User::factory())->finished(score: 2, total: 4)->create();

    Livewire::test(ManageAttempts::class)
        ->callAction('export')
        ->assertFileDownloaded('urinishlar.xlsx');
});
