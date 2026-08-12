<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Ism')
                    ->required(),
                TextInput::make('username')
                    ->label('Login')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->alphaDash(),
                TextInput::make('email')
                    ->label('Email manzili')
                    ->email()
                    ->unique(ignoreRecord: true),
                Select::make('role')
                    ->label('Rol')
                    ->options([
                        'admin' => 'Administrator',
                        'user' => 'Foydalanuvchi',
                    ])
                    ->required()
                    ->default('user'),
            ]);
    }
}
