<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobApplicationResource\Pages;
use App\Filament\Resources\JobApplicationResource\RelationManagers\NotesRelationManager;
use App\Models\JobApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Grouping\Group;

class JobApplicationResource extends Resource
{
    protected static ?string $model = JobApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Отклики';
    protected static ?string $modelLabel = 'Отклик';
    protected static ?string $pluralModelLabel = 'Отклики';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основная информация')->schema([
                    Forms\Components\TextInput::make('company_name')
                        ->label('Компания')
                        ->required(),
                    Forms\Components\TextInput::make('position')
                        ->label('Должность')
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('Статус')
                        ->options([
                            'applied' => 'Отправлено',
                            'interview' => 'Интервью / Тестовое',
                            'offer' => 'Оффер',
                            'rejected' => 'Отказ',
                        ])
                        ->required()
                        ->default('applied'),
                    Forms\Components\DateTimePicker::make('applied_at')
                        ->label('Дата отклика'),
                    Forms\Components\TextInput::make('salary_range')
                        ->label('Зарплатная вилка'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Убрали contentGrid и Stack, теперь это будет строгий список
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Компания')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-m-building-office-2'),

                Tables\Columns\TextColumn::make('position')
                    ->label('Должность')
                    ->searchable()
                    ->wrap() // Перенос длинных названий на новую строку
                    ->description(fn (\App\Models\JobApplication $record): string => $record->jobCategory?->name ?? 'Без категории'), // Выводим категорию мелким текстом под должностью

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'applied'   => 'info',
                        'interview' => 'warning',
                        'offer'     => 'success',
                        'rejected'  => 'danger',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('applied_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->color('gray'),
            ])
            ->defaultSort('applied_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Фильтр по статусу')
                    ->options([
                        'applied' => 'Отправлено',
                        'interview' => 'Интервью',
                        'offer' => 'Оффер',
                        'rejected' => 'Отказ',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobApplications::route('/'),
            'create' => Pages\CreateJobApplication::route('/create'),
            'edit' => Pages\EditJobApplication::route('/{record}/edit'),
        ];
    }
}
