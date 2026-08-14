<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\UserImportReader;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read Schema $form
 */
class ImportUsers extends Page
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.resources.users.pages.import-users';

    protected static ?string $title = 'Excel import';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var list<array{row: int, name: string, username: string, password: string, status: string}>
     */
    public array $rows = [];

    public bool $parsed = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label('Excel fayl')
                    ->required()
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->disk('local')
                    ->directory('imports')
                    ->visibility('private')
                    ->helperText('Ustunlar tartibi: Ism, Login, Parol. Birinchi qator sarlavha deb hisoblanadi.'),
            ])
            ->statePath('data');
    }

    public function preview(): void
    {
        $state = $this->form->getState();

        $path = Storage::disk('local')->path($state['file']);
        $this->rows = UserImportReader::parse($path);
        Storage::disk('local')->delete($state['file']);

        $this->parsed = true;
    }

    public function resetImport(): void
    {
        $this->parsed = false;
        $this->rows = [];
        $this->form->fill();
    }

    public function import(): void
    {
        $newRows = collect($this->rows)->where('status', 'new')->values();

        if ($newRows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($newRows) {
            foreach ($newRows as $row) {
                User::create([
                    'name' => $row['name'],
                    'username' => $row['username'],
                    'password' => Hash::make($row['password']),
                    'plain_password' => $row['password'],
                    'role' => 'user',
                ]);
            }
        });

        $skipped = count($this->rows) - $newRows->count();

        Notification::make()
            ->success()
            ->title("{$newRows->count()} ta foydalanuvchi import qilindi")
            ->body($skipped > 0 ? "{$skipped} ta qator o'tkazib yuborildi (mavjud, takror yoki xato)." : null)
            ->send();

        $this->redirect(UserResource::getUrl('index'));
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'new' => collect($this->rows)->where('status', 'new')->count(),
            'exists' => collect($this->rows)->where('status', 'exists')->count(),
            'duplicate' => collect($this->rows)->where('status', 'duplicate')->count(),
            'invalid' => collect($this->rows)->where('status', 'invalid')->count(),
        ];
    }

    /**
     * A heuristic nudge, not a hard rule: if most rows already exist, this is
     * probably the same file being uploaded again.
     */
    public function looksAlreadyImported(): bool
    {
        if ($this->rows === []) {
            return false;
        }

        return $this->counts()['exists'] / count($this->rows) > 0.7;
    }
}
