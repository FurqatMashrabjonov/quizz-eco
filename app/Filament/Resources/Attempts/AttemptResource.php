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
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttemptResource extends Resource
{
    protected static ?string $model = Attempt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Urinish';

    protected static ?string $pluralModelLabel = 'Urinishlar';

    protected static ?string $navigationLabel = 'Urinishlar';

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Foydalanuvchi')
                    ->searchable(),
                TextColumn::make('user.username')
                    ->label('Login')
                    ->searchable(),
                TextColumn::make('score')
                    ->label("To'g'ri")
                    ->placeholder('-'),
                TextColumn::make('wrong')
                    ->label('Xato')
                    ->getStateUsing(fn (Attempt $record) => $record->isFinished() ? $record->total - $record->score : null)
                    ->placeholder('-'),
                TextColumn::make('total')
                    ->label('Jami'),
                TextColumn::make('percentage')
                    ->label('Ball')
                    ->getStateUsing(fn (Attempt $record) => $record->isFinished() && $record->total > 0
                        ? round($record->score / $record->total * 100).'%'
                        : null)
                    ->placeholder('-'),
                TextColumn::make('started_at')
                    ->label('Boshlangan vaqti')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Tugagan vaqti')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Jarayonda'),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Foydalanuvchi')
                    ->relationship('user', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                // Deleting an attempt frees up the user's quota so they can retake.
                DeleteAction::make()
                    ->modalHeading('Urinishni o\'chirish')
                    ->modalDescription('Urinish va undagi barcha javoblar o\'chiriladi. Bu foydalanuvchiga qaytadan test topshirish imkonini beradi.')
                    ->successNotificationTitle('Urinish o\'chirildi'),
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
