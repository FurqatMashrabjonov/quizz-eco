<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\XlsxExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Excel import')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('gray')
                ->url(UserResource::getUrl('import')),
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
                        ->body('Login va parollarni yuklab olish uchun "Excel eksport" tugmasidan foydalaning.')
                        ->send();
                }),
            Action::make('export')
                ->label('Excel eksport')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn () => XlsxExport::download(
                    'foydalanuvchilar.xlsx',
                    ['Ism', 'Login', 'Parol'],
                    $this->exportRows(),
                )),
            CreateAction::make(),
        ];
    }

    /**
     * @return iterable<array<int, string|null>>
     */
    private function exportRows(): iterable
    {
        // Scoped to the resource query, so admin accounts stay out of the
        // credential sheet that gets printed and handed around.
        foreach (static::getResource()::getEloquentQuery()->orderBy('name')->lazy() as $user) {
            yield [
                $user->name,
                $user->username,
                $user->plain_password,
            ];
        }
    }
}
