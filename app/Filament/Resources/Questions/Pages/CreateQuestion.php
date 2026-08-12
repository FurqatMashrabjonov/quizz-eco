<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Resources\Questions\Schemas\QuestionForm;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateQuestion extends CreateRecord
{
    protected static string $resource = QuestionResource::class;

    protected function beforeCreate(): void
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
