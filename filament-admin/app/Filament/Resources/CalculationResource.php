<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalculationResource\Pages;
use App\Models\Calculation;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CalculationResource extends Resource
{
    protected static ?string $model = Calculation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Ügyfélkapcsolat';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'kalkuláció';

    protected static ?string $pluralModelLabel = 'Mentett kalkulációk';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(3)->schema([
                Infolists\Components\TextEntry::make('customer.name')->label('Ügyfél')->weight('bold')->placeholder('-'),
                Infolists\Components\TextEntry::make('calculator_type')->label('Kalkulátor')->badge()
                    ->formatStateUsing(fn (string $state) => Calculation::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => Calculation::TYPE_COLORS[$state] ?? 'gray'),
                Infolists\Components\TextEntry::make('created_at')->label('Mentve')->dateTime('Y. m. d. H:i')->timezone('Europe/Budapest'),
            ]),
            Infolists\Components\Grid::make(2)->schema([
                Infolists\Components\KeyValueEntry::make('inputs_json')->label('Megadott adatok')
                    ->keyLabel('Mező')->valueLabel('Érték')
                    ->state(fn (Calculation $record) => Calculation::formatFields($record->inputs_json)),
                Infolists\Components\KeyValueEntry::make('results_json')->label('Eredmény')
                    ->keyLabel('Mező')->valueLabel('Érték')
                    ->state(fn (Calculation $record) => Calculation::formatFields($record->results_json)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Mentve')->dateTime('Y. m. d. H:i')
                    ->timezone('Europe/Budapest')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Ügyfél')->searchable()->weight('bold')
                    ->description(fn (Calculation $record) => $record->customer?->email),
                Tables\Columns\TextColumn::make('calculator_type')->label('Kalkulátor')->badge()
                    ->formatStateUsing(fn (string $state) => Calculation::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => Calculation::TYPE_COLORS[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('headline')->label('Fő eredmény')
                    ->state(fn (Calculation $record) => number_format($record->headlineValue(), 0, ',', ' ') . ' Ft'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('calculator_type')->label('Kalkulátor')->options(Calculation::TYPES),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('customer')->label('Ügyfél')->icon('heroicon-m-user')
                    ->visible(fn (Calculation $record) => $record->user_id !== null)
                    ->url(fn (Calculation $record) => CustomerResource::getUrl('view', ['record' => $record->user_id])),
            ])
            ->emptyStateHeading('Még nincs mentett kalkuláció');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCalculations::route('/'),
        ];
    }
}
