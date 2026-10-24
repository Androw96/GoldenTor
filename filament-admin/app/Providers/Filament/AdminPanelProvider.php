<?php

namespace App\Providers\Filament;

use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\URL;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // The app sits behind the static site's .htaccess; make every generated URL
        // (Livewire endpoints, assets, redirects) use the canonical https origin.
        $appUrl = (string) config('app.url');
        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }
        if ($appUrl !== '' && ! app()->runningUnitTests()) {
            URL::forceRootUrl($appUrl);
        }

        // Logged-in admins see the real error instead of a bare "Server Error" page.
        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        if (method_exists($handler, 'renderable')) {
            $handler->renderable(fn (\Throwable $error, \Illuminate\Http\Request $request) => \App\Support\AdminErrorPage::render($error, $request));
        }
    }

    public function panel(Panel $panel): Panel
    {
        $publicUrl = rtrim((string) config('goldentor.public_url'), '/');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('Golden Tor Admin')
            ->brandLogo($publicUrl . '/assets/goldentor_logo_gold.png')
            ->darkModeBrandLogo($publicUrl . '/assets/goldentor_logo_gold.png')
            ->brandLogoHeight('2.6rem')
            ->favicon($publicUrl . '/assets/favicon.png')
            ->colors([
                'primary' => Color::hex('#b8962e'),
                'gray' => Color::Stone,
                'success' => Color::hex('#668272'),
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->font('Montserrat', provider: GoogleFontProvider::class)
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Dark)
            ->maxContentWidth(Width::SevenExtraLarge)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Ügyfélkapcsolat'),
                NavigationGroup::make('Tartalom'),
                NavigationGroup::make('Weboldal')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('Nyilvános oldal')
                    ->url($publicUrl . '/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->group('Weboldal')
                    ->sort(1),
                NavigationItem::make('Régi admin (tartalék)')
                    ->url($publicUrl . '/admin.html', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-archive-box')
                    ->group('Weboldal')
                    ->sort(2),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->unsavedChangesAlerts()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
