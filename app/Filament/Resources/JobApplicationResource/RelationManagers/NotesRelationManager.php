<?php

namespace App\Filament\Resources\JobApplicationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';
    protected static ?string $title = 'Аналитика и заметки';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Тип заметки')
                    ->options([
                        'ai_advice' => 'Совет ИИ',
                        'rejection_reason' => 'Причина отказа',
                        'user_note' => 'Личная заметка',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('content')
                    ->label('Текст')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ai_advice' => 'success',
                        'rejection_reason' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ai_advice' => 'Совет ИИ',
                        'rejection_reason' => 'Отказ',
                        'user_note' => 'Заметка',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('content')
                    ->label('Текст')
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Добавить заметку'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
