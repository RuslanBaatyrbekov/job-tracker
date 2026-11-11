<?php

namespace App\Filament\Pages;

use App\Models\JobCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('job_category_id')
                ->label('Категория откликов')
                ->options(JobCategory::pluck('name', 'id'))
                ->placeholder('Все категории (Общая статистика)')
                ->native(false),
        ])->columns(3);
    }
}
