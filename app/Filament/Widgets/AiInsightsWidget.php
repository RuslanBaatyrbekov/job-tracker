<?php

namespace App\Filament\Widgets;

use App\Models\JobCategory;
use Filament\Widgets\Widget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class AiInsightsWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-insights-widget';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 2;

    use InteractsWithPageFilters;

    public function getStrategyProperty()
    {
        $categoryId = $this->filters['job_category_id'] ?? null;

        $query = JobCategory::whereNotNull('ai_strategy_report');

        if ($categoryId) {
            $query->where('id', $categoryId);
        }

        return $query->orderBy('last_analyzed_at', 'desc')->first();
    }
}
