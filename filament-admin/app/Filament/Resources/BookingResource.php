<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Closure;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Ügyfélkapcsolat';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'foglalás';

    protected static ?string $pluralModelLabel = 'Foglalások';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $count = Booking::query()->where('status', 'requested')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Visszaigazolásra váró időpontkérések';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'topic'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Booking $record */
        return ['Időpont' => $record->slotLabel(), 'Állapot' => Booking::STATUSES[$record->status] ?? $record->status];
    }

    public static function form(Schema $schema): Schema
    {
        $slots = array_combine(config('goldentor.slots'), config('goldentor.slots'));
        $topics = array_combine(config('goldentor.topics'), config('goldentor.topics'));
        $meetings = array_combine(config('goldentor.meetings'), config('goldentor.meetings'));

        return $schema->components([
            \Filament\Schemas\Components\Section::make('Ügyfél')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Név')->required()->maxLength(120),
                    Forms\Components\TextInput::make('email')->label('E-mail')->email()->required()->maxLength(254),
                    Forms\Components\TextInput::make('phone')->label('Telefon')->tel()->maxLength(60),
                ]),
            \Filament\Schemas\Components\Section::make('Konzultáció')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('topic')->label('Téma')->required()->native(false)
                        ->options(fn (?Booking $record) => static::withCurrent($topics, $record?->topic)),
                    Forms\Components\Select::make('meeting')->label('Forma')->required()->native(false)
                        ->options(fn (?Booking $record) => static::withCurrent($meetings, $record?->meeting)),
                    Forms\Components\DatePicker::make('booking_date')->label('Nap')->required()->native(false)
                        ->displayFormat('Y. m. d.')->firstDayOfWeek(1)->closeOnDateSelection(),
                    Forms\Components\Select::make('booking_time')->label('Időpont')->options($slots)->required()->native(false)
                        ->rules([
                            fn (Get $get, ?Booking $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                                if ($get('status') === 'cancelled' || ! $get('booking_date') || ! $value) {
                                    return;
                                }
                                $taken = Booking::query()
                                    ->whereDate('booking_date', $get('booking_date'))
                                    ->where('booking_time', substr((string) $value, 0, 5) . ':00')
                                    ->where('status', '!=', 'cancelled')
                                    ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                    ->exists();
                                if ($taken) {
                                    $fail('Erre a napra és időpontra már van aktív foglalás.');
                                }
                            },
                        ]),
                    Forms\Components\Select::make('status')->label('Állapot')->options(Booking::STATUSES)->default('requested')
                        ->required()->native(false)
                        ->helperText('Visszaigazolás, lezárás vagy lemondás esetén az ügyfél automatikusan e-mailt kap.'),
                    Forms\Components\Textarea::make('message')->label('Üzenet')->rows(4)->maxLength(2000)->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make()
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('Név')->weight('bold'),
                    Infolists\Components\TextEntry::make('email')->label('E-mail')->copyable()
                        ->url(fn (Booking $record) => 'mailto:' . $record->email),
                    Infolists\Components\TextEntry::make('phone')->label('Telefon')->placeholder('-')
                        ->url(fn (Booking $record) => $record->phone ? 'tel:' . preg_replace('/\s+/', '', $record->phone) : null),
                    Infolists\Components\TextEntry::make('booking_date')->label('Nap')->date('Y. m. d., l'),
                    Infolists\Components\TextEntry::make('booking_time')->label('Időpont'),
                    Infolists\Components\TextEntry::make('status')->label('Állapot')->badge()
                        ->formatStateUsing(fn (string $state) => Booking::STATUSES[$state] ?? $state)
                        ->color(fn (string $state) => Booking::STATUS_COLORS[$state] ?? 'gray'),
                    Infolists\Components\TextEntry::make('topic')->label('Téma'),
                    Infolists\Components\TextEntry::make('meeting')->label('Forma'),
                    Infolists\Components\TextEntry::make('created_at')->label('Beérkezett')
                        ->dateTime('Y. m. d. H:i')->timezone('Europe/Budapest'),
                    Infolists\Components\TextEntry::make('message')->label('Üzenet')->placeholder('Nincs üzenet')
                        ->columnSpanFull()->prose(),
                    Infolists\Components\TextEntry::make('public_token')->label('Ügyfélportál')
                        ->formatStateUsing(fn () => 'Ügyfélportál megnyitása ↗')
                        ->url(fn (Booking $record) => $record->portalUrl(), shouldOpenInNewTab: true)
                        ->color('primary'),
                    Infolists\Components\TextEntry::make('reminder_sent_at')->label('Emlékeztető kiküldve')
                        ->dateTime('Y. m. d. H:i')->timezone('Europe/Budapest')->placeholder('Még nem'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy('booking_date')->orderBy('booking_time'))
            ->columns([
                Tables\Columns\TextColumn::make('booking_date')->label('Nap')->date('Y. m. d.')->sortable()
                    ->description(fn (Booking $record) => $record->booking_date?->locale('hu')->isoFormat('dddd')),
                Tables\Columns\TextColumn::make('booking_time')->label('Idő')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Ügyfél')->searchable()->weight('bold')
                    ->description(fn (Booking $record) => $record->email),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('topic')->label('Téma')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('meeting')->label('Forma')->toggleable(),
                Tables\Columns\TextColumn::make('status')->label('Állapot')->badge()->sortable()
                    ->formatStateUsing(fn (string $state) => Booking::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Booking::STATUS_COLORS[$state] ?? 'gray')
                    ->icon(fn (string $state) => Booking::STATUS_ICONS[$state] ?? null),
                Tables\Columns\TextColumn::make('created_at')->label('Beérkezett')->since()->sortable()
                    ->timezone('Europe/Budapest')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Állapot')->options(Booking::STATUSES)->multiple(),
                Tables\Filters\Filter::make('from_today')->label('Mai naptól')->toggle()
                    ->query(fn (Builder $query) => $query->whereDate('booking_date', '>=', now('Europe/Budapest')->toDateString())),
                Tables\Filters\SelectFilter::make('topic')->label('Téma')
                    ->options(fn () => Booking::query()->distinct()->orderBy('topic')->pluck('topic', 'topic')->all()),
            ])
            ->actions([
                \Filament\Actions\Action::make('confirm')->label('Visszaigazol')->icon('heroicon-m-check')->color('success')
                    ->visible(fn (Booking $record) => $record->status === 'requested')
                    ->requiresConfirmation()
                    ->modalDescription('Az ügyfél e-mailben értesítést kap a visszaigazolásról.')
                    ->action(fn (Booking $record) => static::changeStatus($record, 'confirmed')),
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\ViewAction::make(),
                    \Filament\Actions\EditAction::make(),
                    \Filament\Actions\Action::make('complete')->label('Lezárás')->icon('heroicon-m-archive-box')->color('gray')
                        ->visible(fn (Booking $record) => $record->status === 'confirmed')
                        ->requiresConfirmation()
                        ->action(fn (Booking $record) => static::changeStatus($record, 'completed')),
                    \Filament\Actions\Action::make('cancel')->label('Lemondás')->icon('heroicon-m-x-mark')->color('danger')
                        ->visible(fn (Booking $record) => in_array($record->status, ['requested', 'confirmed'], true))
                        ->requiresConfirmation()
                        ->modalDescription('Az időpont felszabadul, az ügyfél e-mailben értesítést kap.')
                        ->action(fn (Booking $record) => static::changeStatus($record, 'cancelled')),
                    \Filament\Actions\Action::make('portal')->label('Ügyfélportál')->icon('heroicon-m-arrow-top-right-on-square')
                        ->url(fn (Booking $record) => $record->portalUrl(), shouldOpenInNewTab: true),
                    \Filament\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulkConfirm')->label('Visszaigazolás')->icon('heroicon-m-check')->color('success')
                        ->requiresConfirmation()->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->where('status', 'requested')
                            ->each(fn (Booking $record) => static::changeStatus($record, 'confirmed', notify: false))),
                    \Filament\Actions\BulkAction::make('bulkComplete')->label('Lezárás')->icon('heroicon-m-archive-box')
                        ->requiresConfirmation()->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->where('status', 'confirmed')
                            ->each(fn (Booking $record) => static::changeStatus($record, 'completed', notify: false))),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nincs megjeleníthető foglalás')
            ->emptyStateDescription('A korábbi foglalásokat az „Összes” fülön találja.')
            ->poll('60s');
    }

    /** Keep values stored by older forms selectable, even if they are no longer in the list. */
    protected static function withCurrent(array $options, ?string $current): array
    {
        if ($current !== null && $current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    public static function changeStatus(Booking $record, string $status, bool $notify = true): void
    {
        $record->status = $status;
        $record->save();

        if ($notify) {
            Notification::make()
                ->title('Foglalás frissítve: ' . (Booking::STATUSES[$status] ?? $status))
                ->body('Az ügyfél e-mail értesítést kapott.')
                ->success()
                ->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBookings::route('/'),
        ];
    }
}
