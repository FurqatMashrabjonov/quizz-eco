<?php

namespace App\Filament\Widgets;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\QuizSetting;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuizStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $settings = QuizSetting::current();

        $users = User::query()->where('role', 'user')->count();
        $usersWithAttempt = Attempt::query()->distinct('user_id')->count('user_id');

        $activeQuestions = Question::query()->where('is_active', true)->count();

        // One pass over attempts covers every attempt-derived figure below.
        $attempts = Attempt::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(finished_at) as finished')
            ->selectRaw('avg(case when total > 0 then score * 100.0 / total end) as average_percentage')
            ->first();

        $inProgress = $attempts->total - $attempts->finished;

        return [
            Stat::make('Foydalanuvchilar', $users)
                ->description($users === 0
                    ? 'Hali foydalanuvchi qo\'shilmagan'
                    : ($users - $usersWithAttempt).' ta hali boshlamagan')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),

            Stat::make('Savollar bazasi', $activeQuestions)
                ->description("Har testda {$settings->questions_per_attempt} ta beriladi")
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color($activeQuestions < $settings->questions_per_attempt ? 'danger' : 'gray'),

            Stat::make('Topshirganlar', $attempts->finished)
                ->description($inProgress > 0
                    ? "{$inProgress} ta hozir jarayonda"
                    : 'Jarayondagi test yo\'q')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($inProgress > 0 ? 'warning' : 'success'),

            Stat::make(
                'O\'rtacha natija',
                $attempts->average_percentage === null ? '—' : round($attempts->average_percentage).'%'
            )
                ->description('Tugatilgan urinishlar bo\'yicha')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color(match (true) {
                    $attempts->average_percentage === null => 'gray',
                    $attempts->average_percentage >= 60 => 'success',
                    default => 'danger',
                }),
        ];
    }
}
