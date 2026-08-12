<?php

namespace App\Filament\Resources\Questions\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class QuestionForm
{
    /**
     * @param  array<int, array{body?: string, is_correct?: bool}>  $options
     */
    public static function hasExactlyOneCorrectOption(array $options): bool
    {
        return collect($options)->where('is_correct', true)->count() === 1;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Savol matni')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Faol')
                    ->default(true)
                    ->required(),
                Repeater::make('options')
                    ->relationship()
                    ->label('Variantlar')
                    ->schema([
                        TextInput::make('body')
                            ->label('Variant')
                            ->required()
                            ->columnSpan(3),
                        Toggle::make('is_correct')
                            ->label("To'g'ri")
                            ->fixIndistinctState()
                            ->columnSpan(1),
                    ])
                    ->columns(4)
                    ->minItems(4)
                    ->maxItems(4)
                    ->defaultItems(4)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
