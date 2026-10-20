<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\CalculationResource;
use App\Models\Calculation;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CalculationsRelationManager extends RelationManager
{
    protected static string $relationship = 'calculations';

    protected static ?string $title = 'Mentett kalkulációk';

    protected static ?string $modelLabel = 'kalkuláció';

    protected static ?string $pluralModelLabel = 'kalkulációk';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return CalculationResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('calculator_type')->label('Típus')->badge()
                    ->formatStateUsing(fn (string $state) => Calculation::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => Calculation::TYPE_COLORS[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('headline')->label('Eredmény')
                    ->state(fn (Calculation $record) => number_format($record->headlineValue(), 0, ',', ' ') . ' Ft'),
                Tables\Columns\TextColumn::make('created_at')->label('Mentve')->dateTime('Y. m. d. H:i')
                    ->timezone('Europe/Budapest')->sortable(),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }
}
