<?php

namespace App\Filament\Widgets;

use App\Models\JobCategory;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ApplicationStatusChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Конверсия (Без учета ожиданий)';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $categoryId = $this->filters['job_category_id'] ?? null;

        // When a category is selected, show only its bar
        $categories = JobCategory::when($categoryId, fn ($q) => $q->where('id', $categoryId))
            ->withCount([
                'jobApplications as interview_count' => fn ($q) => $q->where('status', 'interview'),
                'jobApplications as offer_count' => fn ($q) => $q->where('status', 'offer'),
                'jobApplications as rejected_count' => fn ($q) => $q->where('status', 'rejected'),
            ])->get();

        return [
            'datasets' => [
                [
                    'label' => 'Интервью',
                    'data' => $categories->pluck('interview_count')->toArray(),
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => 'Офферы',
                    'data' => $categories->pluck('offer_count')->toArray(),
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'Отказы',
                    'data' => $categories->pluck('rejected_count')->toArray(),
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $categories->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
        ];
    }
}
