<?php

use App\Filament\Widgets\LatestAttempts;
use App\Filament\Widgets\QuizStatsOverview;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\QuizSetting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('the stats widget counts users, questions, completions and the average score', function () {
    QuizSetting::current()->update(['questions_per_attempt' => 10]);
    Question::factory(12)->create();

    $finished = User::factory()->create();
    $started = User::factory()->create();
    User::factory()->create(); // never started

    Attempt::factory()->for($finished)->finished(score: 8, total: 10)->create();
    Attempt::factory()->for($started)->create();

    Livewire::test(QuizStatsOverview::class)
        ->assertSee('Foydalanuvchilar')
        ->assertSee('3')            // 3 test takers, the admin is excluded
        ->assertSee('1 ta hali boshlamagan')
        ->assertSee('12')           // active questions
        ->assertSee('Har testda 10 ta beriladi')
        ->assertSee('1 ta hozir jarayonda')
        ->assertSee('80%');         // average of the single finished attempt
});

test('the average score ignores attempts that are still in progress', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Attempt::factory()->for($a)->finished(score: 5, total: 10)->create();
    Attempt::factory()->for($b)->create();

    Livewire::test(QuizStatsOverview::class)->assertSee('50%');
});

test('the stats widget copes with an empty database', function () {
    Livewire::test(QuizStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Hali foydalanuvchi qo\'shilmagan');
});

test('the stats widget warns when there are fewer questions than a test needs', function () {
    QuizSetting::current()->update(['questions_per_attempt' => 60]);
    Question::factory(3)->create();

    Livewire::test(QuizStatsOverview::class)
        ->assertSee('Har testda 60 ta beriladi');
});

test('the latest attempts widget lists attempts with their state', function () {
    $user = User::factory()->create(['name' => 'Alisher Nurmatov']);
    $attempt = Attempt::factory()->for($user)->finished(score: 9, total: 10)->create();

    Livewire::test(LatestAttempts::class)
        ->assertCanSeeTableRecords([$attempt])
        ->assertSee('Alisher Nurmatov')
        ->assertSee('9 / 10')
        ->assertSee('90%')
        ->assertSee('Yakunlandi');
});

test('an in-progress attempt is shown as such', function () {
    $attempt = Attempt::factory()->for(User::factory())->create();

    Livewire::test(LatestAttempts::class)
        ->assertCanSeeTableRecords([$attempt])
        ->assertSee('Jarayonda');
});
