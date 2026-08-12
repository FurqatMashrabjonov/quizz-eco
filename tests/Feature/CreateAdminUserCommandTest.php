<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates an admin with a generated password', function () {
    $this->artisan('quiz:create-admin', ['username' => 'boss'])
        ->assertSuccessful();

    $admin = User::query()->where('username', 'boss')->firstOrFail();

    expect($admin->role)->toBe('admin')
        ->and($admin->plain_password)->not->toBeNull()
        ->and(Hash::check($admin->plain_password, $admin->password))->toBeTrue();
});

test('it accepts an explicit password and name', function () {
    $this->artisan('quiz:create-admin', [
        'username' => 'Chief',
        '--name' => 'Bosh admin',
        '--password' => 'sekret123',
    ])->assertSuccessful();

    $admin = User::query()->where('username', 'chief')->firstOrFail();

    expect($admin->name)->toBe('Bosh admin')
        ->and(Hash::check('sekret123', $admin->password))->toBeTrue();
});

test('it refuses to overwrite an existing username', function () {
    User::factory()->create(['username' => 'taken']);

    $this->artisan('quiz:create-admin', ['username' => 'taken'])
        ->assertFailed();

    expect(User::query()->where('username', 'taken')->count())->toBe(1);
});
