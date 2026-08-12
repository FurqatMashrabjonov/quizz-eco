<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('a regular user cannot access the admin panel', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin');

    $response->assertForbidden();
});

test('an admin can access the admin panel', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertSuccessful();
});

test('the panel has no login page of its own', function () {
    expect(Route::has('filament.admin.auth.login'))->toBeFalse();

    $this->get('/admin/login')->assertNotFound();
});

test('a guest sent to the admin panel lands on the single sign-in page', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('an admin signing in is taken to the panel', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'username' => $admin->username,
        'password' => $admin->plain_password,
    ])->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertRedirect('/admin');
});

test('logging out of the panel returns to the site root', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/admin/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
