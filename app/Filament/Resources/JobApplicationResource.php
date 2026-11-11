<?php

namespace App\Filament\Resources;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Filament\Resources\JobApplicationResource\Pages;
use App\Filament\Resources\JobApplicationResource\RelationManagers\NotesRelationManager;
use App\Filament\Resources\JobApplicationResource\RelationManagers\StatusChangesRelationManager;
use App\Models\JobApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                        ->options(ApplicationStatus::class)
                        ->required()
                        ->default(ApplicationStatus::Applied),
                    Forms\Components\Select::make('job_category_id')
                        ->label('Категория / резюме')
                        ->relationship('jobCategory', 'name'),
                    Forms\Components\DateTimePicker::make('applied_at')
                        ->label('Дата отклика')
                        ->default(now()),
                    Forms\Components\TextInput::make('salary_range')
                        ->label('Зарплатная вилка'),
                ])->columns(2),

                // What exactly was sent — this is what the conversion analytics is built on.
                Forms\Components\Section::make('Что и куда отправил')->schema([
                    Forms\Components\Select::make('source')
                        ->label('Источник')
                        ->options(ApplicationSource::class),
                    Forms\Components\TextInput::make('vacancy_url')
                        ->label('Ссылка на вакансию')
                        ->url(),
                    Forms\Components\TextInput::make('location')
                        ->label('Локация'),
                    Forms\Components\Select::make('work_mode')
                        ->label('Формат')
                        ->options(['remote' => 'Remote', 'hybrid' => 'Hybrid', 'onsite' => 'On-site']),
                    Forms\Components\Select::make('language')
                        ->label('Язык вакансии')
                        ->options(['de' => 'Немецкий', 'en' => 'Английский']),
                    Forms\Components\Select::make('german_level')
                        ->label('Требуемый немецкий')
                        ->options(array_combine(
                            ['none', 'A2', 'B1', 'B2', 'C1', 'C2'],
                            ['Не нужен', 'A2', 'B1', 'B2', 'C1', 'C2'],
                        )),
                    Forms\Components\Toggle::make('cover_letter_sent')
                        ->label('Было сопроводительное письмо'),
                    Forms\Components\DateTimePicker::make('follow_up_sent_at')
                        ->label('Follow-up отправлен'),
                    Forms\Components\Textarea::make('job_description')
                        ->label('Текст вакансии')
                        ->rows(6)
                        ->columnSpanFull(),
                ])->columns(2)->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Компания')
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-m-building-office-2')
                    ->url(fn (JobApplication $record) => $record->vacancy_url, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('position')
                    ->label('Должность')
                    ->searchable()
                    ->wrap()
                    ->description(fn (JobApplication $record): string => $record->jobCategory?->name ?? 'Без категории'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Источник')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('cover_letter_sent')
                    ->label('Письмо')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('applied_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Активность')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('applied_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(ApplicationStatus::class),
                Tables\Filters\SelectFilter::make('source')
                    ->label('Источник')
                    ->options(ApplicationSource::class),
                Tables\Filters\SelectFilter::make('job_category_id')
                    ->label('Категория')
                    ->relationship('jobCategory', 'name'),
                Tables\Filters\Filter::make('needs_follow_up')
                    ->label('Ждут ответа > 10 дней')
                    ->query(fn (Builder $query) => $query->awaitingReply()
                        ->where('last_activity_at', '<', now()->subDays(config('job-search.follow_up.remind_after_days')))),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
            StatusChangesRelationManager::class,
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
