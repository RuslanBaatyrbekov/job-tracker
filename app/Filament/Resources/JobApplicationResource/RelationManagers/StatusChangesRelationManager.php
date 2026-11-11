<?php

namespace App\Filament\Resources\JobApplicationResource\RelationManagers;

use App\Enums\ApplicationStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StatusChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusChanges';

    protected static ?string $title = 'История статусов';

    public function table(Table $table): Table
    {
        $label = fn (?string $state) => $state ? (ApplicationStatus::tryFrom($state)?->getLabel() ?? $state) : '—';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('changed_at')->label('Когда')->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('from_status')->label('Было')->formatStateUsing($label)->placeholder('—'),
                Tables\Columns\TextColumn::make('to_status')->label('Стало')->formatStateUsing($label)->badge()
                    ->color(fn (string $state) => ApplicationStatus::tryFrom($state)?->getColor() ?? 'gray'),
                Tables\Columns\TextColumn::make('email.subject')->label('Письмо')->limit(60)->placeholder('вручную'),
            ])
            ->defaultSort('changed_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
