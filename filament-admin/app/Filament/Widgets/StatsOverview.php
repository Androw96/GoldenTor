<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BookingResource;
use App\Filament\Resources\CalculationResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\CustomerResource;
use App\Models\Booking;
use App\Models\Calculation;
use App\Models\Contact;
use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '120s';

    protected function getStats(): array
    {
        $today = now('Europe/Budapest')->toDateString();
        $upcoming = Booking::query()->upcoming()->count();
        $nextBooking = Booking::query()->upcoming()->orderBy('booking_date')->orderBy('booking_time')->first();
        $waiting = Booking::query()->where('status', 'requested')->count();

        $total = Booking::query()->count();
        $converted = Booking::query()->whereIn('status', ['confirmed', 'completed'])->count();
        $conversion = $total ? (int) round($converted / $total * 100) : 0;

        $newContacts = Contact::query()->where('status', 'new')->count();
        $customers = Customer::query()->count();
        $customersRecent = Customer::query()->where('created_at', '>=', now('UTC')->subDays(30))->count();
        $calculationsRecent = Calculation::query()->where('created_at', '>=', now('UTC')->subDays(30))->count();

        return [
            Stat::make('Közelgő konzultációk', $upcoming)
                ->description($nextBooking
                    ? 'Következő: ' . $nextBooking->booking_date?->format('m. d.') . ' ' . $nextBooking->booking_time . ' – ' . $nextBooking->name
                    : 'Nincs közelgő időpont')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->chart($this->dailyBookingsForNextDays($today))
                ->url(BookingResource::getUrl('index')),
            Stat::make('Visszaigazolásra vár', $waiting)
                ->description($waiting ? 'Kattintson a jóváhagyáshoz' : 'Minden kérés kezelve')
                ->descriptionIcon($waiting ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($waiting ? 'warning' : 'success')
                ->url(BookingResource::getUrl('index', ['activeTab' => 'requested'])),
            Stat::make('Új megkeresések', $newContacts)
                ->description('Kapcsolatfelvételi űrlapról')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($newContacts ? 'warning' : 'gray')
                ->url(ContactResource::getUrl('index')),
            Stat::make('Konverzió', $conversion . '%')
                ->description("{$converted} / {$total} foglalás visszaigazolva vagy lezárva")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Ügyfélfiókok', $customers)
                ->description("+{$customersRecent} az elmúlt 30 napban")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info')
                ->url(CustomerResource::getUrl('index')),
            Stat::make('Mentett kalkulációk (30 nap)', $calculationsRecent)
                ->description('Az ügyfelek által mentett számítások')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray')
                ->url(CalculationResource::getUrl('index')),
        ];
    }

    /** @return array<int, int> */
    private function dailyBookingsForNextDays(string $today, int $days = 14): array
    {
        $end = Carbon::parse($today)->addDays($days - 1)->toDateString();
        $counts = Booking::query()
            ->whereIn('status', ['requested', 'confirmed'])
            ->whereDate('booking_date', '>=', $today)
            ->whereDate('booking_date', '<=', $end)
            ->get(['booking_date'])
            ->countBy(fn (Booking $booking) => $booking->booking_date?->toDateString());

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $series[] = (int) ($counts[Carbon::parse($today)->addDays($i)->toDateString()] ?? 0);
        }

        return $series;
    }
}
