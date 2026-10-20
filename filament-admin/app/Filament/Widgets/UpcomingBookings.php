<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingBookings extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Közelgő konzultációk';

    public function table(Table $table): Table
    {
        return $table
            ->query(Booking::query()->upcoming()->orderBy('booking_date')->orderBy('booking_time'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('booking_date')->label('Nap')->date('Y. m. d., D'),
                Tables\Columns\TextColumn::make('booking_time')->label('Idő'),
                Tables\Columns\TextColumn::make('name')->label('Ügyfél')->weight('bold')
                    ->description(fn (Booking $record) => $record->phone ?: $record->email),
                Tables\Columns\TextColumn::make('topic')->label('Téma')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('meeting')->label('Forma'),
                Tables\Columns\TextColumn::make('status')->label('Állapot')->badge()
                    ->formatStateUsing(fn (string $state) => Booking::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Booking::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->actions([
                \Filament\Actions\Action::make('confirm')->label('Visszaigazol')->icon('heroicon-m-check')->color('success')
                    ->visible(fn (Booking $record) => $record->status === 'requested')
                    ->requiresConfirmation()
                    ->modalDescription('Az ügyfél e-mailben értesítést kap a visszaigazolásról.')
                    ->action(fn (Booking $record) => BookingResource::changeStatus($record, 'confirmed')),
                \Filament\Actions\Action::make('open')->label('Megnyitás')->icon('heroicon-m-arrow-right')->color('gray')
                    ->url(fn () => BookingResource::getUrl('index')),
            ])
            ->emptyStateHeading('Nincs közelgő konzultáció')
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
