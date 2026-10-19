<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageBookings extends ManageRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Új foglalás'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Közelgő')
                ->modifyQueryUsing(fn (Builder $query) => $query->upcoming()),
            'requested' => Tab::make('Visszaigazolásra vár')
                ->badge(fn () => Booking::query()->where('status', 'requested')->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'requested')),
            'confirmed' => Tab::make('Visszaigazolt')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'confirmed')),
            'closed' => Tab::make('Lezárt / lemondott')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['completed', 'cancelled'])),
            'all' => Tab::make('Összes'),
        ];
    }
}
