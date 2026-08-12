<?php

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'layout' => [],
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ];
    }

    public function finished(int $score = 0, int $total = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'finished_at' => now(),
            'score' => $score,
            'total' => $total,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
        ]);
    }
}
