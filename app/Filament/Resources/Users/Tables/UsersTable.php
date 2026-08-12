<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Ism')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('Login')
                    ->copyable()
                    ->copyMessage('Nusxalandi')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('plain_password')
                    ->label('Parol')
                    ->copyable()
                    ->copyMessage('Nusxalandi')
                    ->fontFamily('mono'),
                TextColumn::make('attempts_count')
                    ->label('Urinishlar')
                    ->counts('attempts'),
                TextColumn::make('created_at')
                    ->label('Yaratilgan sana')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
