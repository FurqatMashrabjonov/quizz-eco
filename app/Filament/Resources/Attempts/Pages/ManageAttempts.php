<?php

namespace App\Filament\Resources\Attempts\Pages;

use App\Filament\Resources\Attempts\AttemptResource;
use App\Models\Attempt;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Response;

class ManageAttempts extends ManageRecords
{
    protected static string $resource = AttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('CSV eksport')
                ->color('gray')
                ->action(fn () => Response::streamDownload(function () {
                    $handle = fopen('php://output', 'w');
                    fputcsv($handle, ['Ism', 'Login', "To'g'ri", 'Xato', 'Jami', 'Ball %', 'Boshlangan vaqti', 'Tugagan vaqti']);

                    Attempt::query()
                        ->with('user')
                        ->orderByDesc('started_at')
                        ->each(function (Attempt $attempt) use ($handle) {
                            $wrong = $attempt->isFinished() ? $attempt->total - $attempt->score : null;
                            $percentage = $attempt->isFinished() && $attempt->total > 0
                                ? round($attempt->score / $attempt->total * 100).'%'
                                : null;

                            fputcsv($handle, [
                                $attempt->user->name,
                                $attempt->user->username,
                                $attempt->score,
                                $wrong,
                                $attempt->total,
                                $percentage,
                                $attempt->started_at,
                                $attempt->finished_at,
                            ]);
                        });

                    fclose($handle);
                }, 'urinishlar.csv')),
        ];
    }
}
