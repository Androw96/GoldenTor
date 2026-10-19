<?php

namespace App\Filament\Pages;

use Filament\Forms;

class ExpertProfiles extends ContentPage
{
    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Szakértők';

    protected static ?string $title = 'Szakértői profilok';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'szakertok';

    protected static function contentKey(): string
    {
        return 'experts';
    }

    protected static function recordTitle(): string
    {
        return 'Golden Tor szakértői profilok';
    }

    protected function contentSchema(): array
    {
        return [
            Forms\Components\Placeholder::make('hint')->hiddenLabel()
                ->content('Csak ellenőrzött, publikálható adatot adjon meg. A szakertoink.html azokat a profilokat mutatja, ahol a név, a szerep és a bemutatkozás is ki van töltve.'),
            Forms\Components\Repeater::make('profiles')
                ->hiddenLabel()
                ->addActionLabel('Új szakértő')
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): string => ($state['name'] ?? '') ?: 'Új szakértő')
                ->defaultItems(0)
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->label('Név')->required()->maxLength(120),
                    Forms\Components\TextInput::make('role')->label('Szerep / titulus')->required()->maxLength(160),
                    Forms\Components\Textarea::make('bio')->label('Bemutatkozás')->required()->rows(4)->maxLength(1500)->columnSpanFull(),
                    Forms\Components\TagsInput::make('credentials')->label('Képesítések')->columnSpanFull()->placeholder('Új képesítés, majd Enter'),
                    Forms\Components\TextInput::make('photo')->label('Fotó útvonala vagy URL')->placeholder('assets/uploads/szakertok/nev.webp')->maxLength(300),
                    Forms\Components\FileUpload::make('photo_upload')->label('Új fotó feltöltése')
                        ->disk('site')->directory('assets/uploads/szakertok')->visibility('public')
                        ->image()->imageEditor()->imageCropAspectRatio('4:5')->maxSize(4096),
                ]),
        ];
    }

    protected function toFormState(?array $payload, string $language): array
    {
        $profiles = is_array($payload['profiles'] ?? null) ? $payload['profiles'] : [];

        return ['profiles' => array_map(fn ($profile) => [
            'name' => (string) ($profile['name'] ?? ''),
            'role' => (string) ($profile['role'] ?? ''),
            'bio' => (string) ($profile['bio'] ?? ''),
            'credentials' => array_values(array_filter(array_map('strval', (array) ($profile['credentials'] ?? [])), 'strlen')),
            'photo' => (string) ($profile['photo'] ?? ''),
            'photo_upload' => null,
        ], array_values($profiles))];
    }

    protected function toPayload(array $state): array
    {
        $profiles = [];
        foreach (array_values($state['profiles'] ?? []) as $profile) {
            $upload = $profile['photo_upload'] ?? null;
            if (is_array($upload)) {
                $upload = reset($upload) ?: null;
            }
            $profiles[] = [
                'name' => trim((string) ($profile['name'] ?? '')),
                'role' => trim((string) ($profile['role'] ?? '')),
                'bio' => trim((string) ($profile['bio'] ?? '')),
                'credentials' => array_values(array_filter(array_map(fn ($item) => trim((string) $item), (array) ($profile['credentials'] ?? [])), 'strlen')),
                'photo' => $upload ? (string) $upload : trim((string) ($profile['photo'] ?? '')),
            ];
        }

        return ['profiles' => $profiles];
    }
}
