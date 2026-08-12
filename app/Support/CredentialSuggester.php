<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Suggests login credentials for staff accounts.
 *
 * Passwords are printed onto cards and typed by hand on shared machines, so
 * they are plain six-digit codes. That is only ~20 bits of entropy, which is
 * acceptable here because Fortify throttles logins to five attempts per minute
 * per username and IP.
 */
class CredentialSuggester
{
    private const PASSWORD_DIGITS = 6;

    /**
     * Turn a person's name into an unused username, e.g. "Alisher Nurmatov"
     * becomes "alisher.nurmatov" (or "alisher.nurmatov2" if that is taken).
     */
    public function username(string $name): string
    {
        $base = Str::slug($name, '.');

        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = $base.$suffix;
        }

        return $username;
    }

    /**
     * Generate a six-digit password such as "480371".
     */
    public function password(): string
    {
        $digits = '';

        for ($i = 0; $i < self::PASSWORD_DIGITS; $i++) {
            $digits .= random_int(0, 9);
        }

        return $digits;
    }
}
