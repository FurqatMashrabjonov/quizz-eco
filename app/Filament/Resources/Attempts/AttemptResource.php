<?php

namespace App\Filament\Resources\Attempts;

use App\Filament\Resources\Attempts\Pages\ManageAttempts;
use App\Models\Answer;
use App\Models\Attempt;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AttemptResource extends Resource
{
    protected static ?string $model = Attempt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Natija';

    protected static ?string $pluralModelLabel = 'Natijalar';

    protected static ?string $navigationLabel = 'Natijalar';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('Foydalanuvchi'),
                TextEntry::make('user.username')
                    ->label('Login'),
                TextEntry::make('score')
                    ->label("To'g'ri")
                    ->placeholder('-'),
                TextEntry::make('total')
                    ->label('Jami savollar')
                    ->placeholder('-'),
                TextEntry::make('started_at')
                    ->label('Boshlangan vaqti')
                    ->dateTime(),
                TextEntry::make('finished_at')
                    ->label('Tugagan vaqti')
                    ->dateTime()
                    ->placeholder('Jarayonda'),
                RepeatableEntry::make('answers')
                    ->label('Javoblar')
                    // Loaded here rather than in getEloquentQuery() so the list page
                    // isn't made to fetch every answer of every row. loadMissing()
                    // keeps this cheap even though the closure runs per row.
                    ->state(fn (Attempt $record) => $record
                        ->loadMissing(['answers.question.options', 'answers.option'])
                        ->answers)
                    ->columnSpanFull()
                    ->table([
                        TableColumn::make('Savol'),
                        TableColumn::make('Tanlangan javob'),
                        TableColumn::make("To'g'ri javob"),
                        TableColumn::make('Natija'),
                    ])
                    ->schema([
                        TextEntry::make('question.body')
                            ->hiddenLabel(),
                        TextEntry::make('option.body')
                            ->hiddenLabel(),
                        TextEntry::make('correct_option')
                            ->hiddenLabel()
                            ->getStateUsing(fn (Answer $record) => $record->question->options->firstWhere('is_correct', true)?->body),
                        IconEntry::make('option.is_correct')
                            ->hiddenLabel()
                            ->boolean(),
                    ]),
            ]);
    }

    /**
     * Sort on a derived value. The direction is whitelisted rather than
     * interpolated, since it reaches the query as a raw fragment.
     *
     * @return \Closure(Builder, string): Builder
     */
    private static function sortByExpression(string $expression): \Closure
    {
        return fn (Builder $query, string $direction): Builder => $query->orderByRaw(
            $expression.' '.($direction === 'asc' ? 'asc' : 'desc')
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Foydalanuvchi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.username')
                    ->label('Login')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('score')
                    ->label("To'g'ri")
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('wrong')
                    ->label('Xato')
                    ->getStateUsing(fn (Attempt $record) => $record->isFinished() ? $record->total - $record->score : null)
                    ->sortable(query: self::sortByExpression('total - score'))
                    ->placeholder('-'),
                TextColumn::make('total')
                    ->label('Jami')
                    ->sortable(),
                TextColumn::make('percentage')
                    ->label('Ball')
                    ->badge()
                    ->getStateUsing(fn (Attempt $record) => $record->isFinished() && $record->total > 0
                        ? round($record->score / $record->total * 100).'%'
                        : null)
                    // Percentage isn't stored, so ordering is done on the ratio.
                    ->sortable(query: self::sortByExpression('CASE WHEN total > 0 THEN score * 1.0 / total END'))
                    ->color(fn (?string $state) => match (true) {
                        $state === null => 'gray',
                        (int) $state >= 60 => 'success',
                        default => 'danger',
                    })
                    ->placeholder('-'),
                TextColumn::make('started_at')
                    ->label('Boshlangan vaqti')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Tugagan vaqti')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('Jarayonda'),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Foydalanuvchi')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('finished_at')
                    ->label('Holati')
                    ->placeholder('Hammasi')
                    ->trueLabel('Yakunlangan')
                    ->falseLabel('Jarayonda')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('finished_at'),
                        false: fn (Builder $query) => $query->whereNull('finished_at'),
                        blank: fn (Builder $query) => $query,
                    ),
                Filter::make('percentage')
                    ->label('Ball oralig\'i')
                    ->schema([
                        TextInput::make('from')
                            ->label('Dan (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('until')
                            ->label('Gacha (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['from']), fn (Builder $q) => $q
                            ->whereNotNull('finished_at')
                            ->where('total', '>', 0)
                            ->whereRaw('score * 100.0 >= total * ?', [(float) $data['from']]))
                        ->when(filled($data['until']), fn (Builder $q) => $q
                            ->whereNotNull('finished_at')
                            ->where('total', '>', 0)
                            ->whereRaw('score * 100.0 <= total * ?', [(float) $data['until']])))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'])) {
                            $indicators[] = "Ball {$data['from']}% dan";
                        }

                        if (filled($data['until'])) {
                            $indicators[] = "Ball {$data['until']}% gacha";
                        }

                        return $indicators;
                    }),
                Filter::make('started_at')
                    ->label('Sana')
                    ->schema([
                        DatePicker::make('from')->label('Dan'),
                        DatePicker::make('until')->label('Gacha'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('started_at', '>=', $date))
                        ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('started_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from']) {
                            $indicators[] = 'Sana: '.Carbon::parse($data['from'])->format('d.m.Y').' dan';
                        }

                        if ($data['until']) {
                            $indicators[] = 'Sana: '.Carbon::parse($data['until'])->format('d.m.Y').' gacha';
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make(),
                // Deleting an attempt frees up the user's quota so they can retake.
                DeleteAction::make()
                    ->modalHeading('Natijani o\'chirish')
                    ->modalDescription('Natija va undagi barcha javoblar o\'chiriladi. Bu foydalanuvchiga qaytadan test topshirish imkonini beradi.')
                    ->successNotificationTitle('Natija o\'chirildi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttempts::route('/'),
        ];
    }
}
