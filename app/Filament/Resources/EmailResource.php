<?php

namespace App\Filament\Resources;

use App\Enums\EmailStatus;
use App\Filament\Resources\EmailResource\Pages;
use App\Jobs\FilterEmailsViaAiJob;
use App\Models\Email;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static ?string $modelLabel = 'Письмо';

    protected static ?string $pluralModelLabel = 'Письма';

    protected static ?string $navigationGroup = 'Настройки';

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->label('Тема')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('from_address')
                    ->label('Отправитель')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),

                TextColumn::make('date')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(EmailStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Текст')
                    ->icon('heroicon-o-eye')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Email $record) => new HtmlString(
                        '<pre style="white-space:pre-wrap;font-size:12px">'.e($record->body_text ?: 'Тело письма ещё не скачано').'</pre>'
                    )),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Send through the AI pipeline again (e.g. it was wrongly marked irrelevant).
                    Tables\Actions\BulkAction::make('reprocess')
                        ->label('Обработать заново')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (Collection $records) {
                            Email::whereKey($records->modelKeys())->update(['status' => EmailStatus::PendingSubjectScan]);
                            FilterEmailsViaAiJob::dispatch();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListEmails::route('/'),
            'create' => Pages\CreateEmail::route('/create'),
            'edit' => Pages\EditEmail::route('/{record}/edit'),
        ];
    }
}
