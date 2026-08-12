<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Response;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateUsers')
                ->label('Foydalanuvchi yaratish')
                ->color('gray')
                ->schema([
                    TextInput::make('count')
                        ->label('Nechta foydalanuvchi?')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(500)
                        ->default(10),
                ])
                ->action(function (array $data) {
                    User::factory()->count((int) $data['count'])->create();

                    Notification::make()
                        ->success()
                        ->title("{$data['count']} ta foydalanuvchi yaratildi")
                        ->body('Login va parollarni yuklab olish uchun "CSV eksport" tugmasidan foydalaning.')
                        ->send();
                }),
            Action::make('exportCsv')
                ->label('CSV eksport')
                ->color('gray')
                ->action(fn () => Response::streamDownload(function () {
                    $handle = fopen('php://output', 'w');
                    fputcsv($handle, ['Ism', 'Login', 'Parol', 'Rol']);

                    User::query()->orderBy('name')->each(function (User $user) use ($handle) {
                        fputcsv($handle, [
                            $user->name,
                            $user->username,
                            $user->plain_password,
                            $user->role === 'admin' ? 'Administrator' : 'Foydalanuvchi',
                        ]);
                    });

                    fclose($handle);
                }, 'foydalanuvchilar.csv')),
            CreateAction::make(),
        ];
    }
}
