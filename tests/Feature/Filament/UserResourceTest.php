<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('creating a user auto-generates a password instead of accepting one', function () {
    Livewire::test(CreateUser::class)
        ->set('data.name', 'Jane Doe')
        ->set('data.username', 'jane')
        ->set('data.role', 'user')
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'jane')->firstOrFail();

    expect($user->plain_password)->not->toBeNull()
        ->and(Hash::check($user->plain_password, $user->password))->toBeTrue();
});

test('usernames are stored in lowercase regardless of input casing', function () {
    Livewire::test(CreateUser::class)
        ->set('data.name', 'John Doe')
        ->set('data.username', 'JohnDoe')
        ->set('data.role', 'user')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('username', 'johndoe')->exists())->toBeTrue();
});

test('the generate users action bulk-creates users with unique credentials', function () {
    Livewire::test(ListUsers::class)
        ->callAction('generateUsers', data: ['count' => 5]);

    expect(User::query()->count())->toBe(6) // 5 generated + the admin
        ->and(User::query()->whereNotNull('plain_password')->count())->toBe(6);
});

test('the users table shows the plain-text password, not the hash', function () {
    $user = User::factory()->create(['plain_password' => 'sT7xQ2wPmZ']);

    Livewire::test(ListUsers::class)
        ->assertSee('sT7xQ2wPmZ')
        ->assertDontSee($user->password);
});

test('the export csv action downloads a file', function () {
    User::factory()->count(2)->create();

    Livewire::test(ListUsers::class)
        ->callAction('exportCsv')
        ->assertFileDownloaded('foydalanuvchilar.csv');
});
