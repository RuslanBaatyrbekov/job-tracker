<?php

namespace App\Filament\Widgets;

use App\Models\JobApplication;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ApplicationStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $categoryId = $this->filters['job_category_id'] ?? null;
        $total = JobApplication::when($categoryId, fn($q) => $q->where('job_category_id', $categoryId))->count();
        $interviews = JobApplication::when($categoryId, fn($q) => $q->where('job_category_id', $categoryId))->where('status', 'interview')->count();
        $rejected = JobApplication::when($categoryId, fn($q) => $q->where('job_category_id', $categoryId))->where('status', 'rejected')->count();

        return [
            Stat::make('Всего откликов', $total)
                ->description($categoryId ? 'В выбранной категории' : 'Общая активность')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary'),

            Stat::make('Интервью', $interviews)
                ->description('Конверсия в собеседования')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('success'),

            Stat::make('Отказы', $rejected)
                ->description('Требуют анализа ИИ')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
