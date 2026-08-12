<?php

namespace App\Console\Commands;

use App\Models\Option;
use App\Models\Question;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('quiz:seed-questions {count=200 : Nechta savol yaratilsin} {--fresh : Avval mavjud savollarni o\'chirish}')]
#[Description('Generate placeholder questions with four options each, for load testing before real questions are entered.')]
class SeedQuestions extends Command
{
    public function handle(): int
    {
        $count = (int) $this->argument('count');

        if ($count < 1) {
            $this->error('Savollar soni kamida 1 bo\'lishi kerak.');

            return self::FAILURE;
        }

        $existing = Question::query()->count();

        if ($this->option('fresh')) {
            if ($existing > 0 && ! $this->confirmDestructive($existing)) {
                return self::FAILURE;
            }

            Question::query()->delete();
            $this->warn("{$existing} ta eski savol o'chirildi.");
            $existing = 0;
        } elseif ($existing > 0) {
            $this->line("Bazadagi {$existing} ta savol saqlanib qoladi, yangilari ustiga qo'shiladi.");
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        // Chunked so one long transaction doesn't hold locks for the whole run.
        collect(range($existing + 1, $existing + $count))
            ->chunk(50)
            ->each(function ($numbers) use ($bar) {
                DB::transaction(function () use ($numbers, $bar) {
                    foreach ($numbers as $number) {
                        $this->createQuestion($number);
                        $bar->advance();
                    }
                });
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("{$count} ta savol qo'shildi. Bazada jami: ".Question::query()->count());

        return self::SUCCESS;
    }

    /**
     * Placeholder questions are numbered and self-describing so it stays obvious
     * they are filler, and so the correct answer is verifiable during testing.
     */
    private function createQuestion(int $number): void
    {
        $question = Question::create([
            'body' => "Namunaviy savol #{$number}: to'g'ri javobni tanlang.",
            'is_active' => true,
        ]);

        $correct = random_int(1, 4);
        $now = now();

        Option::insert(
            collect(range(1, 4))->map(fn (int $index) => [
                'question_id' => $question->id,
                'body' => $index === $correct
                    ? "Variant {$index} (to'g'ri)"
                    : "Variant {$index}",
                'is_correct' => $index === $correct,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    private function confirmDestructive(int $existing): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        $this->warn("Diqqat: mavjud {$existing} ta savol va ularga berilgan javoblar o'chiriladi.");

        return $this->confirm('Davom etilsinmi?', false);
    }
}
