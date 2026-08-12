<?php

use App\Models\User;
use App\Support\CredentialSuggester;

test('it turns a name into a dotted lowercase username', function (string $name, string $expected) {
    expect(app(CredentialSuggester::class)->username($name))->toBe($expected);
})->with([
    'plain name' => ['Alisher Nurmatov', 'alisher.nurmatov'],
    'apostrophes' => ["Xurshid G'aniyev", 'xurshid.ganiyev'],
    'extra spacing' => ['  Shohruh   Yoldoshev ', 'shohruh.yoldoshev'],
    'single word' => ['Dilnoza', 'dilnoza'],
]);

test('it appends a number when the username is already taken', function () {
    User::factory()->create(['username' => 'alisher.nurmatov']);

    expect(app(CredentialSuggester::class)->username('Alisher Nurmatov'))
        ->toBe('alisher.nurmatov2');

    User::factory()->create(['username' => 'alisher.nurmatov2']);

    expect(app(CredentialSuggester::class)->username('Alisher Nurmatov'))
        ->toBe('alisher.nurmatov3');
});

test('it falls back to a usable username when the name has no latin characters', function () {
    expect(app(CredentialSuggester::class)->username('!!!'))->toBe('user');
});

test('the suggested password is a six-digit code', function () {
    $suggester = app(CredentialSuggester::class);

    foreach (range(1, 50) as $ignored) {
        expect($suggester->password())->toMatch('/^\d{6}$/');
    }
});

test('suggested passwords vary between calls', function () {
    $suggester = app(CredentialSuggester::class);

    $passwords = collect(range(1, 20))->map(fn () => $suggester->password());

    expect($passwords->unique()->count())->toBeGreaterThan(15);
});
