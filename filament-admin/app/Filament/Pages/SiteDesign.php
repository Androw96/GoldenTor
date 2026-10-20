<?php

namespace App\Filament\Pages;

use App\Support\SiteContent;
use Filament\Forms;

class SiteDesign extends ContentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'Design';

    protected static ?string $title = 'Színek és hangulat';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'design';

    protected static function contentKey(): string
    {
        return 'site_design';
    }

    protected static function recordTitle(): string
    {
        return 'Golden Tor design beállítások';
    }

    protected static function isTranslatable(): bool
    {
        return false;
    }

    protected function contentSchema(): array
    {
        return [
            \Filament\Schemas\Components\Section::make('Színek')->columns(4)->schema([
                Forms\Components\ColorPicker::make('navy')->label('Alap sötét')->required(),
                Forms\Components\ColorPicker::make('gold')->label('Kiemelő')->required(),
                Forms\Components\ColorPicker::make('goldLight')->label('Világos kiemelő')->required(),
                Forms\Components\ColorPicker::make('panel')->label('Panel háttér')->required(),
            ]),
            \Filament\Schemas\Components\Section::make('Fejléc hangulata')->columns(2)->schema([
                Forms\Components\TextInput::make('heroDarkness')->label('Fejléc sötétítése (0–1)')
                    ->numeric()->minValue(0)->maxValue(1)->step(0.01)->required(),
                Forms\Components\TextInput::make('goldGlow')->label('Kiemelő fény erőssége (0–1)')
                    ->numeric()->minValue(0)->maxValue(1)->step(0.01)->required(),
            ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('reset')->label('Alapértékek visszaállítása')->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Az űrlap visszaáll az eredeti Golden Tor színekre. Mentés után lép életbe.')
                ->action(fn () => $this->form->fill(['language' => 'hu'] + SiteContent::defaults('site_design'))),
        ];
    }

    protected function toFormState(?array $payload, string $language): array
    {
        $defaults = SiteContent::defaults('site_design');
        $state = array_merge($defaults, array_intersect_key($payload ?? [], $defaults));

        return array_map('strval', $state);
    }

    protected function toPayload(array $state): array
    {
        $defaults = SiteContent::defaults('site_design');
        $payload = [];
        foreach ($defaults as $key => $default) {
            $payload[$key] = trim((string) ($state[$key] ?? $default)) ?: $default;
        }

        return $payload;
    }
}
