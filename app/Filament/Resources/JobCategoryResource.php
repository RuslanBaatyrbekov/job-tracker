<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobCategoryResource\Pages;
use App\Models\JobCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JobCategoryResource extends Resource
{
    protected static ?string $model = JobCategory::class;

    protected static ?string $modelLabel = 'Категория';

    protected static ?string $pluralModelLabel = 'Категории';

    protected static ?string $navigationGroup = 'Настройки';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Настройки категории')->schema([
                Forms\Components\TextInput::make('name')->label('Название')->required(),
                Forms\Components\ColorPicker::make('color')->label('Цвет бейджа'),
                Forms\Components\Textarea::make('resume_text')
                    ->label('Текст резюме под эту категорию (скопируй из CV)')
                    ->rows(10)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Стратегический анализ ИИ')
                ->schema([
                    Forms\Components\Placeholder::make('ai_strategy_report')
                        ->hiddenLabel()
                        ->content(fn ($record) => $record?->ai_strategy_report
                            ? str($record->ai_strategy_report)->markdown()->toHtmlString()
                            : 'Анализ еще не проводился. Добавьте резюме и накопите отказы.'),
                ])
                ->visible(fn ($record) => $record !== null), // only on edit
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Название'),
            Tables\Columns\ColorColumn::make('color')->label('Цвет'),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobCategories::route('/'),
            'create' => Pages\CreateJobCategory::route('/create'),
            'edit' => Pages\EditJobCategory::route('/{record}/edit'),
        ];
    }
}
