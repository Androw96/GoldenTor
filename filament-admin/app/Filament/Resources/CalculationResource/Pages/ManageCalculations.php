<?php

namespace App\Filament\Resources\CalculationResource\Pages;

use App\Filament\Resources\CalculationResource;
use App\Models\Calculation;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageCalculations extends ManageRecords
{
    protected static string $resource = CalculationResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Összes')];
        foreach (Calculation::TYPES as $type => $label) {
            $tabs[$type] = Tab::make($label)->modifyQueryUsing(fn (Builder $query) => $query->where('calculator_type', $type));
        }

        return $tabs;
    }
}
