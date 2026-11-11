<?php

namespace App\Filament\Resources\JobLeadResource\Pages;

use App\Filament\Resources\JobLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJobLeads extends ListRecords
{
    protected static string $resource = JobLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Добавить вакансию')];
    }
}
