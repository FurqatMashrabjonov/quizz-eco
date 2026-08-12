<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->searchable(),
                TextColumn::make('plain_password')
                    ->label('Parol')
                    ->copyable()
                    ->copyMessage('Nusxalandi')
                    ->fontFamily('mono'),
                TextColumn::make('email')
                    ->label('Email manzili')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'admin' ? 'Administrator' : 'Foydalanuvchi')
                    ->color(fn (string $state) => $state === 'admin' ? 'warning' : 'gray')
                    ->searchable(),
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
            ->filters([
                SelectFilter::make('role')
                    ->label('Rol')
                    ->options([
                        'admin' => 'Administrator',
                        'user' => 'Foydalanuvchi',
                    ]),
            ])
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
