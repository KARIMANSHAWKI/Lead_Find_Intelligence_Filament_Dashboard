<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AgentRuns;
use App\Filament\Pages\IcpConfiguration;
use App\Filament\Pages\Overview;
use App\Filament\Pages\Prospects;
use App\Filament\Pages\ViewAgentRun;
use App\Filament\Pages\ViewProspect;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->viteTheme('resources/css/filament/app/theme.css')
            ->login()
            ->authGuard('web')
            ->databaseNotifications()
            ->databaseNotificationsPolling('10s')
            ->brandName('Lead Intelligence')
            ->homeUrl(fn (): string => Overview::getUrl(panel: 'app'))
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->pages([
                Overview::class,
                IcpConfiguration::class,
                Prospects::class,
                AgentRuns::class,
                ViewProspect::class,
                ViewAgentRun::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
