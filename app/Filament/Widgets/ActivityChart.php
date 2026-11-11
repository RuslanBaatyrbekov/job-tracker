<?php

namespace App\Filament\Widgets;

use App\Models\JobApplication;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ActivityChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Динамика откликов (7 дней)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $categoryId = $this->filters['job_category_id'] ?? null;
        $data = [];
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d M');

            $data[] = JobApplication::when($categoryId, fn ($q) => $q->where('job_category_id', $categoryId))
                ->whereDate('applied_at', $date->format('Y-m-d'))
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Отправлено резюме',
                    'data' => $data,
                    'borderColor' => '#3b82f6',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
