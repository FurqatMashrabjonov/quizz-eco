<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuestions extends ListRecords
{
    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Excel import')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('gray')
                ->url(QuestionResource::getUrl('import')),
            CreateAction::make(),
        ];
    }
}
