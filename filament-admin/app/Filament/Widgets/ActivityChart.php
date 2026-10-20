<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\Contact;
use App\Models\Customer;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ActivityChart extends ChartWidget
{
    protected ?string $heading = 'Érdeklődés az elmúlt 12 hónapban';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = ['md' => 2];

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $start = now('UTC')->startOfMonth()->subMonths(11);
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $months[] = $start->copy()->addMonths($i)->format('Y-m');
        }

        $count = function (string $model) use ($start): array {
            $byMonth = $model::query()->where('created_at', '>=', $start)->get(['created_at'])
                ->countBy(fn ($row) => Carbon::parse($row->created_at)->format('Y-m'));

            return $byMonth->all();
        };
        $bookings = $count(Booking::class);
        $contacts = $count(Contact::class);
        $customers = $count(Customer::class);

        return [
            'datasets' => [
                [
                    'label' => 'Időpontkérések',
                    'data' => array_map(fn ($m) => $bookings[$m] ?? 0, $months),
                    'borderColor' => '#b8962e',
                    'backgroundColor' => 'rgba(184, 150, 46, 0.18)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Megkeresések',
                    'data' => array_map(fn ($m) => $contacts[$m] ?? 0, $months),
                    'borderColor' => '#668272',
                    'backgroundColor' => 'rgba(102, 130, 114, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Regisztrációk',
                    'data' => array_map(fn ($m) => $customers[$m] ?? 0, $months),
                    'borderColor' => '#7dd3fc',
                    'backgroundColor' => 'rgba(125, 211, 252, 0.08)',
                    'fill' => false,
                    'tension' => 0.35,
                ],
            ],
            'labels' => array_map(fn ($m) => Carbon::createFromFormat('Y-m', $m)->locale('hu')->translatedFormat('M'), $months),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }
}
