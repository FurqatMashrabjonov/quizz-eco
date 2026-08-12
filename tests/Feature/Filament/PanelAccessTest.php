<?php

use App\Models\User;

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

test('a guest is redirected to the panel login', function () {
    $response = $this->get('/admin');

    $response->assertRedirect('/admin/login');
});
