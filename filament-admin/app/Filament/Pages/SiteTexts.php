<?php

namespace App\Filament\Pages;

use App\Support\SiteContent;
use Filament\Forms;

class SiteTexts extends ContentPage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Oldalszövegek';

    protected static ?string $title = 'Oldalak nyitó szövegei';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'oldalszovegek';

    /** Page keys used by script.js (loadSiteTextOverrides). */
    public const PAGES = [
        'home' => 'Főoldal',
        'befektetes' => 'Befektetés',
        'ingatlan' => 'Ingatlan',
        'finanszirozas' => 'Finanszírozás',
        'biztositas' => 'Biztosítás',
        'szakertoink' => 'Szakértőink',
        'blog' => 'Blog',
        'idopont' => 'Időpontfoglalás',
    ];

    protected static function contentKey(): string
    {
        return 'site_texts';
    }

    protected static function recordTitle(): string
    {
        return 'Weboldal szerkeszthető szövegek';
    }

    protected function contentSchema(): array
    {
        $tabs = [];
        foreach (self::PAGES as $key => $label) {
            $tabs[] = Forms\Components\Tabs\Tab::make($label)->schema([
                Forms\Components\TextInput::make("pages.{$key}.eyebrow")->label('Felső kis cím')->maxLength(120),
                Forms\Components\TextInput::make("pages.{$key}.title")->label('Főcím')->maxLength(180),
                Forms\Components\Textarea::make("pages.{$key}.lead")->label('Bevezető')->rows(4)->maxLength(800),
            ]);
        }

        return [
            Forms\Components\Placeholder::make('hint')->hiddenLabel()
                ->content('Az üresen hagyott mező esetén az oldal a beépített szöveget mutatja.'),
            Forms\Components\Tabs::make('pages_tabs')->tabs($tabs)->persistTabInQueryString(),
        ];
    }

    protected function toFormState(?array $payload, string $language): array
    {
        $defaults = $language === 'hu' ? SiteContent::defaults('site_texts') : [];
        $pages = [];
        foreach (array_keys(self::PAGES) as $key) {
            $value = $payload[$key] ?? $defaults[$key] ?? [];
            $pages[$key] = [
                'eyebrow' => (string) ($value['eyebrow'] ?? ''),
                'title' => (string) ($value['title'] ?? ''),
                'lead' => (string) ($value['lead'] ?? ''),
            ];
        }

        return ['pages' => $pages];
    }

    protected function toPayload(array $state): array
    {
        $payload = [];
        foreach (array_keys(self::PAGES) as $key) {
            $value = array_map(fn ($text) => trim((string) $text), $state['pages'][$key] ?? []);
            $value = array_filter($value, 'strlen');
            if ($value !== []) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
