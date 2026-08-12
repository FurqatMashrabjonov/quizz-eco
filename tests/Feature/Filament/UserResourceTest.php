<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('the admin enters a name, login and password to create a user', function () {
    Livewire::test(CreateUser::class)
        ->set('data.name', 'Jane Doe')
        ->set('data.username', 'jane')
        ->set('data.password', '480371')
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('username', 'jane')->firstOrFail();

    expect($user->plain_password)->toBe('480371')
        ->and(Hash::check('480371', $user->password))->toBeTrue();
});

test('entering a name suggests a login and password', function () {
    $component = Livewire::test(CreateUser::class)
        ->set('data.name', 'Alisher Nurmatov');

    expect($component->get('data.username'))->toBe('alisher.nurmatov')
        ->and($component->get('data.password'))->toMatch('/^\d{6}$/');
});

test('suggestions never overwrite what the admin already typed', function () {
    $component = Livewire::test(CreateUser::class)
        ->set('data.username', 'qoshimcha')
        ->set('data.password', '111222')
        ->set('data.name', 'Alisher Nurmatov');

    expect($component->get('data.username'))->toBe('qoshimcha')
        ->and($component->get('data.password'))->toBe('111222');
});

test('users created through the panel are never admins', function () {
    Livewire::test(CreateUser::class)
        ->set('data.name', 'Jane Doe')
        ->set('data.username', 'jane')
        ->set('data.password', '480371')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('username', 'jane')->value('role'))->toBe('user');
});

test('usernames are stored in lowercase regardless of input casing', function () {
    Livewire::test(CreateUser::class)
        ->set('data.name', 'John Doe')
        ->set('data.username', 'JohnDoe')
        ->set('data.password', '480371')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('username', 'johndoe')->exists())->toBeTrue();
});

test('the edit form shows the readable password, never the hash', function () {
    $user = User::factory()->create(['plain_password' => '480371']);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertSet('data.password', '480371');
});

test('editing a password keeps the readable copy in sync', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->set('data.password', '915204')
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->plain_password)->toBe('915204')
        ->and(Hash::check('915204', $user->password))->toBeTrue();
});

test('admins are hidden from the user list', function () {
    $user = User::factory()->create(['name' => 'Oddiy xodim']);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->assertCanNotSeeTableRecords([$this->admin]);
});

test('the generate users action bulk-creates users with unique credentials', function () {
    Livewire::test(ListUsers::class)
        ->callAction('generateUsers', data: ['count' => 5]);

    expect(User::query()->where('role', 'user')->count())->toBe(5)
        ->and(User::query()->where('role', 'user')->whereNotNull('plain_password')->count())->toBe(5);
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
