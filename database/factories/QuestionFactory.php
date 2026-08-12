<?php

namespace Database\Factories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->sentence().'?',
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Question $question) {
            $correct = fake()->numberBetween(0, 3);

            for ($i = 0; $i < 4; $i++) {
                $question->options()->create([
                    'body' => fake()->words(3, true),
                    'is_correct' => $i === $correct,
                ]);
            }
        });
    }
}
