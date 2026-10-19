<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Ügyfélkapcsolat';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'ügyfél';

    protected static ?string $pluralModelLabel = 'Ügyfélfiókok';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Fiókadatok')->columns(3)->schema([
                Forms\Components\TextInput::make('name')->label('Név')->required()->maxLength(120),
                Forms\Components\TextInput::make('email')->label('E-mail')->email()->required()->maxLength(254)
                    ->unique(table: 'users', column: 'email', ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state) => mb_strtolower(trim((string) $state))),
                Forms\Components\TextInput::make('phone')->label('Telefon')->tel()->maxLength(40),
            ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(4)->schema([
                Infolists\Components\TextEntry::make('name')->label('Név')->weight('bold'),
                Infolists\Components\TextEntry::make('email')->label('E-mail')->copyable()
                    ->url(fn (Customer $record) => 'mailto:' . $record->email),
                Infolists\Components\TextEntry::make('phone')->label('Telefon')->placeholder('-'),
                Infolists\Components\TextEntry::make('created_at')->label('Regisztrált')
                    ->dateTime('Y. m. d. H:i')->timezone('Europe/Budapest'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['calculations', 'feedback']))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->placeholder('-')->searchable(),
                Tables\Columns\TextColumn::make('calculations_count')->label('Kalkulációk')->badge()->color('success')->sortable(),
                Tables\Columns\TextColumn::make('feedback_count')->label('Visszajelzések')->badge()->color('info')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Regisztrált')->dateTime('Y. m. d.')
                    ->timezone('Europe/Budapest')->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_calculations')->label('Van mentett kalkulációja')->toggle()
                    ->query(fn (Builder $query) => $query->has('calculations')),
                Tables\Filters\Filter::make('recent')->label('Elmúlt 30 nap')->toggle()
                    ->query(fn (Builder $query) => $query->where('created_at', '>=', now('UTC')->subDays(30))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    static::setPasswordAction(Tables\Actions\Action::make('setPassword')),
                    Tables\Actions\DeleteAction::make()
                        ->modalDescription('A fiók, a mentett kalkulációk és a visszajelzések véglegesen törlődnek.'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Sets a new password the customer can use on fiok.html. The website verifies with
     * PHP password_verify(), which accepts Laravel's bcrypt hashes.
     *
     * @template T of \Filament\Actions\Action|\Filament\Tables\Actions\Action
     *
     * @param  T  $action
     * @return T
     */
    public static function setPasswordAction($action)
    {
        return $action
            ->label('Új jelszó beállítása')
            ->icon('heroicon-m-key')
            ->color('warning')
            ->modalDescription('Az ügyfél a régi jelszavával többé nem tud belépni. Az új jelszót biztonságos csatornán adja át.')
            ->form([
                Forms\Components\TextInput::make('password')->label('Új jelszó')->password()->revealable()->required()
                    ->minLength(10)->maxLength(128)
                    ->confirmed(),
                Forms\Components\TextInput::make('password_confirmation')->label('Új jelszó még egyszer')->password()->required(),
            ])
            ->action(function (Customer $record, array $data): void {
                $record->forceFill(['password_hash' => Hash::make($data['password'])])->save();
                Notification::make()->title('Jelszó frissítve')->success()->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CalculationsRelationManager::class,
            RelationManagers\FeedbackRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
