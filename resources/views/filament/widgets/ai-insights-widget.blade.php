<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-x-3 mb-4">
            <x-filament::icon icon="heroicon-o-sparkles" class="h-6 w-6 text-amber-500" />
            <h2 class="text-lg font-bold">Актуальный инсайт ИИ</h2>
        </div>
        <div class="prose dark:prose-invert max-w-none text-sm">
            @if($this->strategy)
                <p class="text-gray-400 mb-2">Фокус-категория: <strong>{{ $this->strategy->name }}</strong></p>
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    {!! nl2br(e($this->strategy->ai_strategy_report)) !!}
                </div>
            @else
                <p class="text-gray-500">Стратегия еще не сформирована. Ожидайте ночного анализа или запустите ai:strategy.</p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
