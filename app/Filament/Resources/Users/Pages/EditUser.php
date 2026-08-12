<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\CredentialSuggester;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * `password` holds a hash, so the form is filled from the readable copy
     * instead — otherwise the admin would be shown the bcrypt string.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Read from the record rather than $data: `plain_password` is hidden on
        // the model, so it never reaches the array Filament passes in here.
        $data['password'] = $this->getRecord()->plain_password ?? '';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['password'] ?? null)) {
            $data['plain_password'] = $data['password'];
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regeneratePassword')
                ->label('Yangi parol')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (CredentialSuggester $suggester) {
                    $password = $suggester->password();

                    $this->record->update([
                        'password' => $password,
                        'plain_password' => $password,
                    ]);

                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Parol yangilandi')
                        ->body("Yangi parol: {$password}")
                        ->persistent()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
