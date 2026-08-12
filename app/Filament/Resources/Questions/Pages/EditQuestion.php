<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Resources\Questions\Schemas\QuestionForm;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditQuestion extends EditRecord
{
    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        if (! QuestionForm::hasExactlyOneCorrectOption($this->data['options'] ?? [])) {
            Notification::make()
                ->danger()
                ->title("Aynan bitta variant to'g'ri deb belgilanishi kerak")
                ->send();

            $this->halt();
        }
    }
}
