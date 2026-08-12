<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\CredentialSuggester;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Ism')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, CredentialSuggester $suggester) {
                        if (blank($state)) {
                            return;
                        }

                        // Only fill in blanks, so anything the admin typed stays put.
                        if (blank($get('username'))) {
                            $set('username', $suggester->username($state));
                        }

                        if (blank($get('password'))) {
                            $set('password', $suggester->password());
                        }
                    }),
                TextInput::make('username')
                    ->label('Login')
                    ->helperText('Ism kiritilgach avtomatik taklif qilinadi.')
                    ->required()
                    ->unique(ignoreRecord: true)
                    // Uppercase is accepted because the model lowercases it on save.
                    ->rule('regex:/^[A-Za-z0-9._-]+$/')
                    ->validationMessages([
                        'regex' => 'Login faqat lotin harflari, raqamlar, nuqta va chiziqchadan iborat bo\'lishi mumkin.',
                    ]),
                TextInput::make('password')
                    ->label('Parol')
                    ->helperText('Foydalanuvchiga shu ko\'rinishda beriladi.')
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->minLength(6),
            ]);
    }
}
