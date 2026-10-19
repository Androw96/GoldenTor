<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Áttekintés';

    public function getColumns(): int | string | array
    {
        return ['md' => 3];
    }
}
