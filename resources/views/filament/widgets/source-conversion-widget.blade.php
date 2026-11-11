<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Конверсия по источникам</x-slot>
        <x-slot name="description">Где отклики превращаются в интервью. Источник указывается в отклике или берётся из лида.</x-slot>

        @if (empty($this->rows))
            <p class="text-sm text-gray-500">Пока нет данных.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4">Источник</th>
                            <th class="py-2 pr-4 text-right">Откликов</th>
                            <th class="py-2 pr-4 text-right">Ответили</th>
                            <th class="py-2 pr-4 text-right">Интервью</th>
                            <th class="py-2 pr-4 text-right">Офферы</th>
                            <th class="py-2 text-right">Без ответа</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows as $source => $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-medium">{{ $source }}</td>
                                <td class="py-2 pr-4 text-right">{{ $row['applications'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $row['responded'] }} <span class="text-gray-400">({{ $row['response_rate'] }}%)</span></td>
                                <td class="py-2 pr-4 text-right font-semibold">{{ $row['interviews'] }} <span class="text-gray-400 font-normal">({{ $row['interview_rate'] }}%)</span></td>
                                <td class="py-2 pr-4 text-right">{{ $row['offers'] }}</td>
                                <td class="py-2 text-right">{{ $row['no_response'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
