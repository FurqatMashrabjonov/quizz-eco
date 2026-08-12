<?php

use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Models\Question;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('a question can be created with exactly one correct option', function () {
    Livewire::test(CreateQuestion::class)
        ->set('data.body', 'What is 2 + 2?')
        ->set('data.is_active', true)
        ->set('data.options', [
            ['body' => '3', 'is_correct' => false],
            ['body' => '4', 'is_correct' => true],
            ['body' => '5', 'is_correct' => false],
            ['body' => '6', 'is_correct' => false],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $question = Question::query()->where('body', 'What is 2 + 2?')->firstOrFail();

    expect($question->options)->toHaveCount(4)
        ->and($question->options()->where('is_correct', true)->count())->toBe(1);
});

test('a question cannot be created with zero correct options', function () {
    Livewire::test(CreateQuestion::class)
        ->set('data.body', 'What is 2 + 2?')
        ->set('data.is_active', true)
        ->set('data.options', [
            ['body' => '3', 'is_correct' => false],
            ['body' => '4', 'is_correct' => false],
            ['body' => '5', 'is_correct' => false],
            ['body' => '6', 'is_correct' => false],
        ])
        ->call('create')
        ->assertNotified();

    expect(Question::query()->where('body', 'What is 2 + 2?')->exists())->toBeFalse();
});
