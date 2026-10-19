<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeedbackResource\Pages;
use App\Models\Feedback;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Ügyfélkapcsolat';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'visszajelzés';

    protected static ?string $pluralModelLabel = 'Visszajelzések';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function stars(?int $rating): string
    {
        $rating = max(0, min(5, (int) $rating));

        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(3)->schema([
                Infolists\Components\TextEntry::make('customer.name')->label('Ügyfél')->weight('bold'),
                Infolists\Components\TextEntry::make('customer.email')->label('E-mail')->copyable(),
                Infolists\Components\TextEntry::make('created_at')->label('Dátum')->dateTime('Y. m. d. H:i')->timezone('Europe/Budapest'),
                Infolists\Components\TextEntry::make('expert')->label('Szakértő'),
                Infolists\Components\TextEntry::make('rating')->label('Értékelés')->color('primary')
                    ->formatStateUsing(fn ($state) => static::stars((int) $state)),
                Infolists\Components\TextEntry::make('message')->label('Üzenet')->columnSpanFull()->prose(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Dátum')->dateTime('Y. m. d.')->timezone('Europe/Budapest')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Ügyfél')->searchable()->weight('bold')
                    ->description(fn (Feedback $record) => $record->customer?->email),
                Tables\Columns\TextColumn::make('expert')->label('Szakértő')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('rating')->label('Értékelés')->sortable()->color('primary')
                    ->formatStateUsing(fn ($state) => static::stars((int) $state)),
                Tables\Columns\TextColumn::make('message')->label('Üzenet')->limit(80)->wrap()->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rating')->label('Értékelés')
                    ->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']),
                Tables\Filters\SelectFilter::make('expert')->label('Szakértő')
                    ->options(fn () => Feedback::query()->distinct()->orderBy('expert')->pluck('expert', 'expert')->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Még nincs visszajelzés');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFeedback::route('/'),
        ];
    }
}
