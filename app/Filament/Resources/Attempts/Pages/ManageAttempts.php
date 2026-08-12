<?php

namespace App\Filament\Resources\Attempts\Pages;

use App\Filament\Resources\Attempts\AttemptResource;
use App\Models\Attempt;
use App\Support\XlsxExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageAttempts extends ManageRecords
{
    protected static string $resource = AttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Excel eksport')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn () => XlsxExport::download(
                    'urinishlar.xlsx',
                    ['Ism', 'Login', "To'g'ri", 'Xato', 'Jami', 'Ball %', 'Boshlangan vaqti', 'Tugagan vaqti'],
                    $this->exportRows(),
                )),
        ];
    }

    /**
     * @return iterable<array<int, string|int|null>>
     */
    private function exportRows(): iterable
    {
        foreach (Attempt::query()->with('user')->orderByDesc('started_at')->lazy() as $attempt) {
            yield [
                $attempt->user->name,
                $attempt->user->username,
                $attempt->score,
                $attempt->isFinished() ? $attempt->total - $attempt->score : null,
                $attempt->total,
                $attempt->isFinished() && $attempt->total > 0
                    ? (int) round($attempt->score / $attempt->total * 100)
                    : null,
                $attempt->started_at?->format('d.m.Y H:i'),
                $attempt->finished_at?->format('d.m.Y H:i'),
            ];
        }
    }
}
