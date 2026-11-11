<?php

namespace App\Filament\Resources;

use App\Enums\ApplicationSource;
use App\Enums\LeadStatus;
use App\Exceptions\AiRequestException;
use App\Filament\Resources\JobLeadResource\Pages;
use App\Jobs\ScoreLeadJob;
use App\Models\JobLead;
use App\Services\LeadActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class JobLeadResource extends Resource
{
    protected static ?string $model = JobLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationLabel = 'Вакансии (лиды)';

    protected static ?string $modelLabel = 'Вакансия';

    protected static ?string $pluralModelLabel = 'Вакансии';

    protected static ?int $navigationSort = 0;

    public static function getNavigationBadge(): ?string
    {
        $count = JobLead::where('status', LeadStatus::New)
            ->where('score', '>=', config('job-search.leads.digest_min_score'))
            ->count();

        return $count ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('title')->label('Должность')->required(),
                Forms\Components\TextInput::make('company_name')->label('Компания'),
                Forms\Components\TextInput::make('location')->label('Локация'),
                Forms\Components\TextInput::make('url')->label('Ссылка')->url(),
                Forms\Components\Select::make('source')->label('Источник')->options(ApplicationSource::class)->default(ApplicationSource::Other),
                Forms\Components\Select::make('status')->label('Статус')->options(LeadStatus::class)->default(LeadStatus::New),
                Forms\Components\Select::make('job_category_id')->label('Резюме')->relationship('jobCategory', 'name'),
                Forms\Components\Textarea::make('description')
                    ->label('Текст вакансии')
                    ->helperText('Чем полнее текст, тем точнее оценка и сопроводительное письмо.')
                    ->rows(8)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->badge()
                    ->sortable()
                    ->placeholder('…')
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 75 => 'success',
                        $state >= 55 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('Вакансия')
                    ->searchable()
                    ->wrap()
                    ->weight('bold')
                    ->url(fn (JobLead $record) => $record->url, shouldOpenInNewTab: true)
                    ->description(fn (JobLead $record) => Str::limit((string) $record->score_reason, 140)),
                Tables\Columns\TextColumn::make('company_name')->label('Компания')->searchable(),
                Tables\Columns\TextColumn::make('location')->label('Локация')->toggleable(),
                Tables\Columns\TextColumn::make('work_mode')->label('Формат')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('german_level')->label('DE')->toggleable(),
                Tables\Columns\TextColumn::make('source')->label('Источник')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->label('Статус')->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('Найдена')->since()->sortable(),
            ])
            ->defaultSort('score', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(LeadStatus::class)
                    ->default(LeadStatus::New->value),
                Tables\Filters\Filter::make('good')
                    ->label('Только хорошие (score ≥ порога)')
                    ->query(fn ($query) => $query->where('score', '>=', config('job-search.leads.digest_min_score'))),
            ])
            ->actions([
                Tables\Actions\Action::make('tailor')
                    ->label('Письмо')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->modalHeading(fn (JobLead $record) => 'Под вакансию: '.$record->title)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(function (JobLead $record) {
                        try {
                            $text = app(LeadActions::class)->tailor($record);
                        } catch (AiRequestException $e) {
                            $text = 'ИИ недоступен: '.$e->getMessage();
                        }

                        return new HtmlString('<div class="prose dark:prose-invert max-w-none text-sm">'.Str::markdown(e($text)).'</div>');
                    }),
                Tables\Actions\Action::make('applied')
                    ->label('Откликнулся')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (JobLead $record) => $record->status !== LeadStatus::Applied)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Toggle::make('cover_letter_sent')->label('Отправил сопроводительное письмо')
                            ->default(fn (JobLead $record) => filled($record->tailoring)),
                    ])
                    ->action(function (JobLead $record, array $data) {
                        $application = app(LeadActions::class)->markApplied($record, (bool) $data['cover_letter_sent']);
                        Notification::make()->success()->title("Отклик #{$application->id} создан")->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('rescore')
                        ->label('Пересчитать score')
                        ->icon('heroicon-o-arrow-path')
                        ->action(fn (JobLead $record) => ScoreLeadJob::dispatch($record->id)),
                    Tables\Actions\Action::make('skip')
                        ->label('Пропустить')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn (JobLead $record) => app(LeadActions::class)->skip($record)),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('skip')
                        ->label('Пропустить')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn ($records) => JobLead::whereKey($records->modelKeys())->update(['status' => LeadStatus::Skipped]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobLeads::route('/'),
            'create' => Pages\CreateJobLead::route('/create'),
            'edit' => Pages\EditJobLead::route('/{record}/edit'),
        ];
    }
}
