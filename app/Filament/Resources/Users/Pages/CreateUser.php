<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * The `password` attribute is hashed by a model cast, so the readable copy
     * has to be captured before it is written.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['plain_password'] = $data['password'];
        $data['role'] = 'user';

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Foydalanuvchi yaratildi')
            ->body("Login: {$this->record->username} · Parol: {$this->record->plain_password}")
            ->persistent();
    }
}
