<?php

use App\Filament\Resources\Attempts\Pages\ManageAttempts;
use App\Models\Attempt;
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

test('the export csv action downloads a file', function () {
    Attempt::factory()->for(User::factory())->finished(score: 2, total: 4)->create();

    Livewire::test(ManageAttempts::class)
        ->callAction('exportCsv')
        ->assertFileDownloaded('urinishlar.csv');
});
