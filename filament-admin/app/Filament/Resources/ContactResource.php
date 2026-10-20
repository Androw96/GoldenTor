<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Ügyfélkapcsolat';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'megkeresés';

    protected static ?string $pluralModelLabel = 'Megkeresések';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $count = Contact::query()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'topic', 'message'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->label('Név')->required()->maxLength(120),
            Forms\Components\TextInput::make('email')->label('E-mail')->email()->required()->maxLength(254),
            Forms\Components\TextInput::make('phone')->label('Telefon')->tel()->maxLength(60),
            Forms\Components\TextInput::make('topic')->label('Téma')->required()->maxLength(120),
            Forms\Components\Select::make('status')->label('Állapot')->options(Contact::STATUSES)->default('new')->required()->native(false),
            Forms\Components\Textarea::make('message')->label('Üzenet')->required()->rows(6)->maxLength(2000)->columnSpanFull(),
        ])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make()->columns(3)->schema([
                Infolists\Components\TextEntry::make('name')->label('Név')->weight('bold'),
                Infolists\Components\TextEntry::make('email')->label('E-mail')->copyable()
                    ->url(fn (Contact $record) => 'mailto:' . $record->email),
                Infolists\Components\TextEntry::make('phone')->label('Telefon')->placeholder('-'),
                Infolists\Components\TextEntry::make('topic')->label('Téma')->badge()->color('gray'),
                Infolists\Components\TextEntry::make('status')->label('Állapot')->badge()
                    ->formatStateUsing(fn (string $state) => Contact::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Contact::STATUS_COLORS[$state] ?? 'gray'),
                Infolists\Components\TextEntry::make('created_at')->label('Beérkezett')
                    ->dateTime('Y. m. d. H:i')->timezone('Europe/Budapest'),
                Infolists\Components\TextEntry::make('message')->label('Üzenet')->columnSpanFull()->prose(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Beérkezett')->dateTime('Y. m. d. H:i')
                    ->timezone('Europe/Budapest')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->weight('bold')
                    ->description(fn (Contact $record) => $record->email),
                Tables\Columns\TextColumn::make('topic')->label('Téma')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('message')->label('Üzenet')->limit(70)->wrap()->searchable()
                    ->tooltip(fn (Contact $record) => $record->message),
                Tables\Columns\SelectColumn::make('status')->label('Állapot')->options(Contact::STATUSES)
                    ->selectablePlaceholder(false)->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Állapot')->options(Contact::STATUSES)->multiple(),
            ])
            ->actions([
                \Filament\Actions\Action::make('reply')->label('Válasz')->icon('heroicon-m-envelope')->color('primary')
                    ->url(fn (Contact $record) => 'mailto:' . $record->email . '?subject=' . rawurlencode('Re: ' . $record->topic . ' – Golden Tor Consulting')),
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\EditAction::make(),
                    \Filament\Actions\Action::make('done')->label('Lezárás')->icon('heroicon-m-check')->color('success')
                        ->visible(fn (Contact $record) => $record->status !== 'done')
                        ->action(fn (Contact $record) => $record->update(['status' => 'done'])),
                    \Filament\Actions\Action::make('archive')->label('Archiválás')->icon('heroicon-m-archive-box')->color('gray')
                        ->visible(fn (Contact $record) => $record->status !== 'archived')
                        ->action(fn (Contact $record) => $record->update(['status' => 'archived'])),
                    \Filament\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('markDone')->label('Lezárás')->icon('heroicon-m-check')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'done'])),
                    \Filament\Actions\BulkAction::make('markArchived')->label('Archiválás')->icon('heroicon-m-archive-box')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'archived'])),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Még nincs megkeresés')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageContacts::route('/'),
        ];
    }
}
