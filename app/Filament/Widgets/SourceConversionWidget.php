<?php

namespace App\Filament\Widgets;

use App\Services\FunnelStats;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Which channel actually brings interviews. The single most useful table for planning the week.
 */
class SourceConversionWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'filament.widgets.source-conversion-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 6;

    public function getRowsProperty(): array
    {
        return app(FunnelStats::class)->bySource($this->filters['job_category_id'] ?? null);
    }
}
