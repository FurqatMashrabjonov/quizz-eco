<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuizSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        QuizSetting::current();

        // Demo accounts use a known weak password and the questions are lorem
        // ipsum, so they must never reach production. Use `quiz:create-admin`
        // there instead.
        if (app()->isProduction()) {
            $this->command?->warn('Production detected: seeded settings only. Run `php artisan quiz:create-admin <login>` to create an admin.');

            return;
        }

        User::factory()->admin()->create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'plain_password' => 'password',
        ]);

        $demoUser = User::factory()->create([
            'name' => 'Demo User',
            'username' => 'demo',
            'email' => 'demo@example.com',
            'password' => bcrypt('password'),
            'plain_password' => 'password',
        ]);

        $this->command?->info("Demo user login: {$demoUser->username} / password");

        Question::factory(60)->create();
    }
}
