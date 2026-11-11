<?php

namespace App\Filament\Resources\JobApplicationResource\Pages;

use App\Filament\Resources\JobApplicationResource;
use App\Models\JobApplication;
use App\Models\JobCategory;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;

class ListJobApplications extends ListRecords
{
    protected static string $resource = JobApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('ai_strategy')
                ->label('Обновить AI-стратегию')
                ->icon('heroicon-m-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Глобальный анализ')
                ->modalDescription('ИИ проанализирует отказы и ваши резюме по всем категориям. Результаты появятся в настройках каждой категории.')
                ->action(function () {
                    Artisan::call('ai:strategy');

                    Notification::make()
                        ->success()
                        ->title('Анализ завершен')
                        ->body('Отчеты ИИ обновлены. Перейдите в раздел "Категории", чтобы прочитать рекомендации.')
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Все')->icon('heroicon-m-list-bullet')];

        $categories = JobCategory::all();

        // One grouped query instead of two counts per category.
        $counts = JobApplication::selectRaw('job_category_id, count(*) as total')
            ->groupBy('job_category_id')
            ->pluck('total', 'job_category_id');

        foreach ($categories as $category) {
            $tabs['category_'.$category->id] = Tab::make($category->name)
                ->badge($counts[$category->id] ?? 0)
                ->icon('heroicon-m-folder')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('job_category_id', $category->id));
        }

        return $tabs;
    }
}
