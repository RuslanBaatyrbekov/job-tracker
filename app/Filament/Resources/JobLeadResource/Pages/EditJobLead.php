<?php

namespace App\Filament\Resources\JobLeadResource\Pages;

use App\Filament\Resources\JobLeadResource;
use App\Jobs\ScoreLeadJob;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJobLead extends EditRecord
{
    protected static string $resource = JobLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        // Description added/changed -> score it again with the full text.
        if ($this->record->wasChanged('description')) {
            ScoreLeadJob::dispatch($this->record->id);
        }
    }
}
