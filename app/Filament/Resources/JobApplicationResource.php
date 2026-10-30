<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobApplicationResource\Pages;
use App\Models\JobApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\Split;

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
            // Превращаем таблицу в сетку карточек!
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->columns([
                Stack::make([
                    Split::make([
                        Tables\Columns\TextColumn::make('company_name')
                            ->weight('bold')
                            ->size('lg')
                            ->searchable(),
                        Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'applied' => 'info',
                                'interview' => 'warning',
                                'offer' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                    ]),
                    Tables\Columns\TextColumn::make('position')
                        ->color('gray')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('applied_at')
                        ->dateTime('d M Y H:i')
                        ->color('gray')
                        ->size('sm'),
                ])->space(2),
            ])
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
            ])
            ->defaultSort('applied_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Позже мы добавим сюда Relation Manager для вывода советов ИИ (таблица application_notes)
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
