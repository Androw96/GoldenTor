<?php

namespace App\Filament\Pages;

use App\Support\SiteContent;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Base for pages that edit one JSON record of the legacy `content` table
 * (the same records the old admin.html wrote through /api/admin/content/...).
 *
 * @property Form $form
 */
abstract class ContentPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.content-page';

    protected static ?string $navigationGroup = 'Tartalom';

    /** Form state. */
    public ?array $data = [];

    public string $language = 'hu';

    abstract protected static function contentKey(): string;

    abstract protected static function recordTitle(): string;

    /** @return array<\Filament\Forms\Components\Component> */
    abstract protected function contentSchema(): array;

    /** Stored payload -> form state (without the language field). */
    abstract protected function toFormState(?array $payload, string $language): array;

    /** Form state -> payload to store. */
    abstract protected function toPayload(array $state): array;

    /** Pages that only exist in one language (e.g. design) return false. */
    protected static function isTranslatable(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->loadLanguage($this->language);
    }

    public function loadLanguage(string $language): void
    {
        $this->language = array_key_exists($language, SiteContent::LANGUAGES) ? $language : 'hu';
        $payload = SiteContent::get(static::contentKey(), $this->language);
        $this->form->fill(['language' => $this->language] + $this->toFormState($payload, $this->language));
    }

    public function form(Form $form): Form
    {
        $languageField = Forms\Components\ToggleButtons::make('language')
            ->label('Nyelv')
            ->options(SiteContent::LANGUAGES)
            ->inline()
            ->live()
            ->afterStateUpdated(fn (?string $state) => $this->loadLanguage($state ?: 'hu'))
            ->helperText('Nyelvváltáskor a nem mentett módosítások elvesznek.')
            ->visible(static::isTranslatable());

        return $form
            ->schema([$languageField, ...$this->contentSchema()])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $language = static::isTranslatable() ? ($state['language'] ?? $this->language) : 'hu';
        unset($state['language']);

        SiteContent::put(static::contentKey(), $language, static::recordTitle(), $this->toPayload($state));

        Notification::make()
            ->title('Mentve')
            ->body('A nyilvános oldalon frissítés után látszik.')
            ->success()
            ->send();

        $this->loadLanguage($language);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Mentés')->submit('save')->keyBindings(['mod+s']),
        ];
    }

    public function getSubheading(): ?string
    {
        $updated = SiteContent::updatedAt(static::contentKey(), static::isTranslatable() ? $this->language : 'hu');

        return $updated
            ? 'Utoljára mentve: ' . Carbon::parse($updated, 'UTC')->timezone('Europe/Budapest')->format('Y. m. d. H:i')
            : 'Még nincs mentett változat – az alapértelmezett tartalom látható.';
    }
}
