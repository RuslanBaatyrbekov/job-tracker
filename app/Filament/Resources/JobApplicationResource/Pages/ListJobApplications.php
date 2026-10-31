<?php

namespace App\Filament\Resources\JobApplicationResource\Pages;

use App\Filament\Resources\JobApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\JobCategory;

class ListJobApplications extends ListRecords
{
    protected static string $resource = JobApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Все отклики')];

        $categories = JobCategory::all();

        foreach ($categories as $category) {
            $tabs[$category->id] = Tab::make($category->name)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('job_category_id', $category->id));
        }

        return $tabs;
    }
}
