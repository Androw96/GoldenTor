<?php

namespace App\Filament\Pages;

use App\Support\Diagnostics;
use Filament\Pages\Page;

class SystemStatus extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Weboldal';

    protected static ?string $navigationLabel = 'Rendszerállapot';

    protected static ?string $title = 'Rendszerállapot';

    protected static ?string $slug = 'rendszerallapot';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.system-status';

    protected function getViewData(): array
    {
        // Page rendering is not probed here (it would render this page recursively).
        return Diagnostics::report(probe: false);
    }
}
