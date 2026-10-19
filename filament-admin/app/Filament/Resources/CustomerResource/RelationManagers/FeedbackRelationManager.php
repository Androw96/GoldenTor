<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FeedbackRelationManager extends RelationManager
{
    protected static string $relationship = 'feedback';

    protected static ?string $title = 'Visszajelzések';

    protected static ?string $modelLabel = 'visszajelzés';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('expert')->label('Szakértő'),
                Tables\Columns\TextColumn::make('rating')->label('Értékelés')
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state) . str_repeat('☆', 5 - (int) $state))
                    ->color('primary'),
                Tables\Columns\TextColumn::make('message')->label('Üzenet')->limit(80)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('Dátum')->dateTime('Y. m. d.')->timezone('Europe/Budapest'),
            ]);
    }
}
