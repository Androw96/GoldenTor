<?php

namespace App\Filament\Pages;

use App\Support\SiteContent;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

class BlogPosts extends ContentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Blog';

    protected static ?string $title = 'Blogbejegyzések';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'blog';

    protected static function contentKey(): string
    {
        return 'blog_posts';
    }

    protected static function recordTitle(): string
    {
        return 'Golden Tor blogbejegyzések';
    }

    protected function contentSchema(): array
    {
        return [
            Forms\Components\Repeater::make('posts')
                ->hiddenLabel()
                ->addActionLabel('Új blogcikk')
                ->reorderable()
                ->reorderableWithButtons()
                ->cloneable()
                ->collapsible()
                ->collapsed()
                ->itemLabel(fn (array $state): string => (($state['status'] ?? '') === 'draft' ? '[Vázlat] ' : '') . (($state['title'] ?? '') ?: 'Új blogcikk'))
                ->defaultItems(0)
                ->columns(6)
                ->schema([
                    Forms\Components\TextInput::make('title')->label('Cím')->required()->maxLength(180)->columnSpan(4)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    Forms\Components\Select::make('status')->label('Állapot')->native(false)->columnSpan(2)
                        ->options(['published' => 'Publikált', 'draft' => 'Vázlat'])->default('draft')->required(),
                    Forms\Components\TextInput::make('slug')->label('URL azonosító')->columnSpan(2)
                        ->helperText('Üresen hagyva a címből készül.')->maxLength(120),
                    Forms\Components\TextInput::make('category')->label('Kategória')->columnSpan(2)->maxLength(60)
                        ->datalist(['Befektetés', 'Ingatlan', 'Finanszírozás', 'Biztosítás', 'Élethelyzet', 'Golden Tor']),
                    Forms\Components\TextInput::make('date')->label('Dátum (megjelenő szöveg)')->columnSpan(2)
                        ->placeholder('2026. október')->maxLength(40),
                    Forms\Components\TextInput::make('image')->label('Kép útvonala vagy URL')->columnSpan(3)
                        ->placeholder('assets/uploads/blog/kep.webp')->maxLength(300),
                    Forms\Components\FileUpload::make('image_upload')->label('Új kép feltöltése')->columnSpan(3)
                        ->disk('site')->directory('assets/uploads/blog')->visibility('public')
                        ->image()->imageEditor()->maxSize(4096)
                        ->helperText('Feltöltés után ez lesz a cikk képe.'),
                    Forms\Components\Textarea::make('excerpt')->label('Rövid bevezető')->rows(3)->maxLength(600)->columnSpanFull(),
                    Forms\Components\TagsInput::make('points')->label('Kiemelt pontok')->columnSpanFull()
                        ->placeholder('Új pont, majd Enter')->reorderable(),
                    Forms\Components\Textarea::make('body')->label('Teljes cikk')->rows(10)->columnSpanFull()
                        ->helperText('A bekezdéseket üres sorral válassza el.'),
                ]),
        ];
    }

    protected function toFormState(?array $payload, string $language): array
    {
        $posts = $payload['posts'] ?? null;
        if (! is_array($posts) || $posts === []) {
            $posts = $language === 'hu' ? (SiteContent::defaults('blog_posts')['posts'] ?? []) : [];
        }

        return ['posts' => array_map(fn ($post) => [
            'status' => ($post['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
            'title' => (string) ($post['title'] ?? ''),
            'slug' => (string) ($post['slug'] ?? ''),
            'category' => (string) ($post['category'] ?? ''),
            'date' => (string) ($post['date'] ?? ''),
            'image' => (string) ($post['image'] ?? ''),
            'image_upload' => null,
            'excerpt' => (string) ($post['excerpt'] ?? ''),
            'points' => array_values(array_filter(array_map('strval', (array) ($post['points'] ?? [])), 'strlen')),
            'body' => implode("\n\n", array_map('strval', (array) ($post['body'] ?? []))),
        ], array_values($posts))];
    }

    /** Mirrors normalizeBlogPost() of the previous admin, so blog.html reads it unchanged. */
    protected function toPayload(array $state): array
    {
        $posts = [];
        foreach (array_values($state['posts'] ?? []) as $index => $post) {
            $title = trim((string) ($post['title'] ?? '')) ?: 'Új blogcikk';
            $upload = $post['image_upload'] ?? null;
            if (is_array($upload)) {
                $upload = reset($upload) ?: null;
            }
            $posts[] = [
                'status' => ($post['status'] ?? '') === 'draft' ? 'draft' : 'published',
                'slug' => trim((string) ($post['slug'] ?? '')) ?: (Str::slug($title) ?: 'blogcikk-' . ($index + 1)),
                'category' => trim((string) ($post['category'] ?? '')) ?: 'Golden Tor',
                'date' => trim((string) ($post['date'] ?? '')) ?: now('Europe/Budapest')->locale('hu')->translatedFormat('Y. F'),
                'image' => $upload ? (string) $upload : trim((string) ($post['image'] ?? '')),
                'title' => $title,
                'excerpt' => trim((string) ($post['excerpt'] ?? '')),
                'points' => array_values(array_filter(array_map(fn ($point) => trim((string) $point), (array) ($post['points'] ?? [])), 'strlen')),
                'body' => array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', str_replace("\r\n", "\n", (string) ($post['body'] ?? ''))) ?: []), 'strlen')),
            ];
        }

        return ['posts' => $posts];
    }
}
