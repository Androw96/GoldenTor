<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use Filament\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageContacts extends ManageRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Új megkeresés'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Nyitott')
                ->badge(fn () => Contact::query()->whereIn('status', ['new', 'in_progress'])->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['new', 'in_progress'])),
            'done' => Tab::make('Lezárt')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'done')),
            'archived' => Tab::make('Archivált')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'archived')),
            'all' => Tab::make('Összes'),
        ];
    }
}
