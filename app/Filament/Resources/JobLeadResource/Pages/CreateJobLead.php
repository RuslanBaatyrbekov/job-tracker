<?php

namespace App\Filament\Resources\JobLeadResource\Pages;

use App\Filament\Resources\JobLeadResource;
use App\Jobs\ScoreLeadJob;
use Filament\Resources\Pages\CreateRecord;

class CreateJobLead extends CreateRecord
{
    protected static string $resource = JobLeadResource::class;

    protected function afterCreate(): void
    {
        ScoreLeadJob::dispatch($this->record->id);
    }
}
