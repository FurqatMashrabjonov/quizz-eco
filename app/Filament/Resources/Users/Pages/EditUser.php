<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regeneratePassword')
                ->label('Parolni yangilash')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $password = Str::password(10);

                    $this->record->update([
                        'password' => Hash::make($password),
                        'plain_password' => $password,
                    ]);

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
