<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    public function getSubheading(): ?string
    {
        return 'A weboldalon (fiok.html) regisztrált személyes fiókok. Új fiókot az ügyfél maga hoz létre.';
    }
}
