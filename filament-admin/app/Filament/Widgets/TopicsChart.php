<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\ChartWidget;

class TopicsChart extends ChartWidget
{
    protected ?string $heading = 'Foglalások témák szerint';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $topics = Booking::query()->get(['topic'])->countBy('topic')->sortDesc()->take(6);

        return [
            'datasets' => [[
                'label' => 'Foglalások',
                'data' => $topics->values()->all(),
                'backgroundColor' => ['#b8962e', '#668272', '#ebf150', '#7dd3fc', '#a8a29e', '#f472b6'],
                'borderWidth' => 0,
            ]],
            'labels' => $topics->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
