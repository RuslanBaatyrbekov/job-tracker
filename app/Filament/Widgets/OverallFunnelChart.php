<?php

namespace App\Filament\Widgets;

use App\Models\JobApplication;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OverallFunnelChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Соотношение статусов';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $categoryId = $this->filters['job_category_id'] ?? null;

        $stats = JobApplication::when($categoryId, fn($q) => $q->where('job_category_id', $categoryId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'datasets' => [
                [
                    'data' => [
                        $stats['applied'] ?? 0,
                        $stats['interview'] ?? 0,
                        $stats['offer'] ?? 0,
                        $stats['rejected'] ?? 0,
                    ],
                    'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
                ],
            ],
            'labels' => ['В ожидании', 'Интервью', 'Офферы', 'Отказы'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
