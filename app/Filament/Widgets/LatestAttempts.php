<?php

namespace App\Filament\Widgets;

use App\Models\Attempt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestAttempts extends TableWidget
{
    protected static ?string $heading = 'Oxirgi urinishlar';

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Attempt::query()->with('user')->latest('started_at'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Hali urinishlar yo\'q')
            ->emptyStateDescription('Foydalanuvchilar testni boshlagach shu yerda ko\'rinadi.')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Foydalanuvchi')
                    ->searchable(),
                TextColumn::make('user.username')
                    ->label('Login')
                    ->fontFamily('mono'),
                TextColumn::make('score')
                    ->label('Natija')
                    ->formatStateUsing(fn (Attempt $record) => $record->isFinished()
                        ? "{$record->score} / {$record->total}"
                        : '—'),
                TextColumn::make('percentage')
                    ->label('Foiz')
                    ->badge()
                    ->getStateUsing(fn (Attempt $record) => $record->isFinished() && $record->total > 0
                        ? round($record->score / $record->total * 100).'%'
                        : null)
                    ->color(fn (?string $state) => match (true) {
                        $state === null => 'gray',
                        (int) $state >= 60 => 'success',
                        default => 'danger',
                    })
                    ->placeholder('—'),
                TextColumn::make('started_at')
                    ->label('Boshlandi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Holat')
                    ->formatStateUsing(fn (?string $state) => $state ? 'Yakunlandi' : 'Jarayonda')
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'success' : 'warning')
                    ->placeholder('Jarayonda'),
            ]);
    }
}
