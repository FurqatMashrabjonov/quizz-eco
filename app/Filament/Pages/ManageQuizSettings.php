<?php

namespace App\Filament\Pages;

use App\Models\QuizSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class ManageQuizSettings extends Page
{
    protected string $view = 'filament.pages.manage-quiz-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Test sozlamalari';

    protected static ?string $navigationLabel = 'Sozlamalar';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(QuizSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('duration_minutes')
                    ->label('Test davomiyligi (daqiqa)')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('questions_per_attempt')
                    ->label('Har bir urinishdagi savollar soni')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('max_attempts')
                    ->label('Foydalanuvchi uchun urinishlar soni')
                    ->numeric()
                    ->required()
                    ->minValue(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        QuizSetting::current()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Sozlamalar saqlandi')
            ->send();
    }
}
