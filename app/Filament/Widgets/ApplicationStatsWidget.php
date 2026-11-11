<?php

namespace App\Filament\Widgets;

use App\Services\FunnelStats;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApplicationStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $categoryId = $this->filters['job_category_id'] ?? null;
        $funnel = app(FunnelStats::class);
        $stats = $funnel->summary($categoryId);
        $week = $funnel->appliedThisWeek($categoryId);
        $goal = config('job-search.weekly_goal');

        return [
            Stat::make('Отклики на этой неделе', "{$week} / {$goal}")
                ->description($week >= $goal ? 'Цель недели выполнена' : 'До цели: '.($goal - $week))
                ->descriptionIcon('heroicon-m-flag')
                ->color($week >= $goal ? 'success' : 'warning'),
            Stat::make('Всего откликов', $stats['applications'])
                ->description("Ждут ответа: {$stats['waiting']} · без ответа: {$stats['no_response']}")
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary'),
            Stat::make('Отвечают', $stats['response_rate'].'%')
                ->description("{$stats['responded']} живых ответов (интервью или отказ)")
                ->descriptionIcon('heroicon-m-envelope-open')
                ->color('info'),
            Stat::make('Доходят до интервью', $stats['interview_rate'].'%')
                ->description("Интервью: {$stats['interviews']} · офферы: {$stats['offers']}")
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('success'),
        ];
    }
}
