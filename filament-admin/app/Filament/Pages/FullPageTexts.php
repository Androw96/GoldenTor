<?php

namespace App\Filament\Pages;

use App\Support\PageLayouts;
use App\Support\PageTexts;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Every editable text of the public website, page by page. The same data is edited
 * directly on the website with the "Szövegek szerkesztése" button (site-text.js).
 */
class FullPageTexts extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Tartalom';

    protected static ?string $navigationLabel = 'Teljes szövegek';

    protected static ?string $title = 'A weboldal összes szövege';

    protected static ?string $slug = 'teljes-szovegek';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.full-page-texts';

    public const PAGE_LABELS = [
        'index' => 'Főoldal', 'header' => 'Fejléc (minden oldal)', 'footer' => 'Lábléc (minden oldal)',
        'befektetes' => 'Befektetés', 'ingatlan' => 'Ingatlan', 'finanszirozas' => 'Finanszírozás',
        'biztositas' => 'Biztosítás', 'szakertoink' => 'Szakértőink', 'blog' => 'Blog', 'idopont' => 'Időpontfoglalás',
        'kalkulatorok' => 'Kalkulátorok', 'fiok' => 'Személyes fiók', 'ugyfelportal' => 'Ügyfélportál',
        'adatkezeles' => 'Adatkezelés', 'impresszum' => 'Impresszum', 'jogi-nyilatkozat' => 'Jogi nyilatkozat',
        'preindex' => 'Nyitókapu', 'welcome' => 'Üdvözlő oldal',
    ];

    /** Form state. */
    public ?array $data = [];

    #[Locked]
    public string $selectedPage = 'index';

    #[Locked]
    public bool $onlyChanged = false;

    public function mount(): void
    {
        $requested = (string) request()->query('oldal', 'index');
        $this->selectedPage = array_key_exists($requested, $this->pageOptions()) ? $requested : 'index';
        $this->onlyChanged = request()->query('csak') === 'modositott';
        $this->fillForm();
    }

    /** @return array<string, string> page prefix => label with counts */
    public function pageOptions(): array
    {
        $saved = PageTexts::saved();
        $counts = [];
        foreach (PageTexts::manifest() as $key => $entry) {
            $page = $entry['page'];
            $counts[$page] ??= [0, 0];
            $counts[$page][0]++;
            if (isset($saved[$key])) {
                $counts[$page][1]++;
            }
        }
        $order = array_flip(array_keys(self::PAGE_LABELS));
        uksort($counts, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99) ?: strcmp($a, $b));

        $options = [];
        foreach ($counts as $page => [$total, $changed]) {
            $options[$page] = (self::PAGE_LABELS[$page] ?? Str::headline($page)) . " – {$total} szöveg" . ($changed ? ", {$changed} módosítva" : '');
        }

        return $options;
    }

    /** @return array<string, array{page: string, tag: string, rich: bool, text: string}> */
    public function entries(): array
    {
        $saved = PageTexts::saved();

        return array_filter(
            PageTexts::manifest(),
            fn ($entry, $key) => $entry['page'] === $this->selectedPage && (! $this->onlyChanged || isset($saved[$key])),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public static function field(string $key): string
    {
        return 'f_' . str_replace(['.', '-'], ['__', '_'], $key);
    }

    public function fillForm(): void
    {
        $saved = PageTexts::saved();
        $state = [];
        foreach ($this->entries() as $key => $entry) {
            $state[self::field($key)] = $saved[$key]['v'] ?? $entry['text'];
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $saved = PageTexts::saved();
        $fields = [];
        foreach ($this->entries() as $key => $entry) {
            $changed = isset($saved[$key]);
            $plainOriginal = trim(strip_tags(str_replace('<br>', ' ', $entry['text'])));
            $help = $changed ? 'Módosítva. Eredeti: „' . Str::limit($plainOriginal, 160) . '”' : null;
            if ($entry['rich']) {
                $help = trim(($help ? $help . ' · ' : '') . 'Formázás: <br> sortörés, <em>/<strong> kiemelés, <a href="…"> link.');
            }
            $fields[] = Textarea::make(self::field($key))
                ->label(($changed ? '● ' : '') . '#' . Str::after($key, '.') . ' · ' . $this->tagLabel($entry['tag']))
                ->rows(max(1, min(8, (int) ceil(mb_strlen($entry['text']) / 90))))
                ->autosize()
                ->maxLength(PageTexts::MAX_LENGTH)
                ->placeholder('Üresen hagyva az eredeti szöveg áll vissza')
                ->helperText($help);
        }

        return $schema->components($fields)->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $saved = PageTexts::saved();
        $changes = [];
        foreach ($this->entries() as $key => $entry) {
            $value = (string) ($state[self::field($key)] ?? '');
            $current = $saved[$key]['v'] ?? $entry['text'];
            if (trim($value) === trim($current)) {
                continue;
            }
            $changes[$key] = trim($value) === ''
                ? ['reset' => true]
                : ['value' => $value, 'rich' => $entry['rich'], 'original' => $entry['text']];
        }

        if (! $changes) {
            Notification::make()->title('Nincs változás')->info()->send();

            return;
        }

        PageTexts::apply($changes, 'admin', auth()->user()?->email);
        Notification::make()
            ->title(count($changes) . ' szöveg mentve')
            ->body('A weboldalon frissítés után látszik.')
            ->success()
            ->send();
        $this->fillForm();
    }

    public function publicUrl(bool $edit = true): string
    {
        $page = in_array($this->selectedPage, ['header', 'footer', 'index'], true) ? '' : $this->selectedPage . '.html';

        return rtrim((string) config('goldentor.public_url'), '/') . '/' . $page . ($edit ? '?gt-edit=1' : '');
    }

    public function pageUrl(string $page, ?bool $onlyChanged = null): string
    {
        $query = ['oldal' => $page];
        if ($onlyChanged ?? $this->onlyChanged) {
            $query['csak'] = 'modositott';
        }

        return static::getUrl($query);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editOnSite')->label('Szerkesztés a weboldalon')->icon('heroicon-m-cursor-arrow-rays')
                ->url(fn () => $this->publicUrl(), shouldOpenInNewTab: true),
            Action::make('resetLayout')->label('Szerkezet visszaállítása')->icon('heroicon-m-squares-2x2')->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Az oldalon végzett szerkezeti módosítások (sorrend, elrejtés, másolatok, képek, linkek) törlődnek, az eredeti felépítés áll vissza. A szövegek maradnak.')
                ->visible(fn () => ! PageLayouts::isEmpty(PageLayouts::forPrefix($this->selectedPage)))
                ->action(function (): void {
                    PageLayouts::reset($this->selectedPage);
                    Notification::make()->title('Szerkezet visszaállítva')->success()->send();
                }),
            Action::make('resetPage')->label('Szövegek visszaállítása')->icon('heroicon-m-arrow-uturn-left')->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Az oldal összes módosított szövege visszaáll az eredetire.')
                ->visible(fn () => count(array_intersect_key(PageTexts::saved(), $this->entries())) > 0)
                ->action(function (): void {
                    $keys = array_keys(array_intersect_key(PageTexts::saved(), $this->entries()));
                    PageTexts::apply(array_fill_keys($keys, ['reset' => true]), 'admin', auth()->user()?->email);
                    Notification::make()->title(count($keys) . ' szöveg visszaállítva')->success()->send();
                    $this->fillForm();
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        $layout = PageLayouts::forPrefix($this->selectedPage);
        if (PageLayouts::isEmpty($layout)) {
            return null;
        }
        $parts = array_filter([
            count($layout['hidden'] ?? []) ? count($layout['hidden']) . ' elrejtett elem' : null,
            count($layout['clones'] ?? []) ? count($layout['clones']) . ' másolat' : null,
            count($layout['order'] ?? []) ? 'módosított sorrend' : null,
            count($layout['images'] ?? []) ? count($layout['images']) . ' cserélt kép' : null,
            count($layout['links'] ?? []) ? count($layout['links']) . ' módosított link' : null,
        ]);

        return 'Szerkezeti módosítások ezen az oldalon: ' . implode(', ', $parts) . '. Ezeket a weboldalon, az „Oldal szerkesztése” módban lehet módosítani.';
    }

    private function tagLabel(string $tag): string
    {
        return match (true) {
            in_array($tag, ['h1', 'h2'], true) => 'főcím',
            in_array($tag, ['h3', 'h4', 'h5', 'h6'], true) => 'alcím',
            $tag === 'p' => 'bekezdés',
            $tag === 'li' => 'listaelem',
            in_array($tag, ['a', 'button'], true) => 'gomb / link',
            default => 'szöveg',
        };
    }
}
