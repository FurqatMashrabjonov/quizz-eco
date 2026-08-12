<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('quiz:create-admin {username} {--name=} {--password=}')]
#[Description('Create an admin user. Safe to run in production; unlike db:seed it adds no demo data.')]
class CreateAdminUser extends Command
{
    public function handle(): int
    {
        $username = Str::lower($this->argument('username'));

        if (User::query()->where('username', $username)->exists()) {
            $this->error("A user with the username [{$username}] already exists.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(12);

        $admin = User::create([
            'name' => $this->option('name') ?: $username,
            'username' => $username,
            'password' => Hash::make($password),
            'plain_password' => $password,
            'role' => 'admin',
        ]);

        $this->info('Admin created.');
        $this->table(['Login', 'Parol'], [[$admin->username, $password]]);

        return self::SUCCESS;
    }
}
