<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $password = Str::password(10);

        $data['password'] = Hash::make($password);
        $data['plain_password'] = $password;

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
