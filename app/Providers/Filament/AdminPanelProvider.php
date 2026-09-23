<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Widgets\ResumenOperativo;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->revealablePasswords()
            ->colors([
                'primary' => Color::Amber,
            ])
            // Selector claro / oscuro nativo de Filament. Se declara de
            // forma explícita para que no dependa de los valores por defecto
            // del panel al actualizar Filament.
            ->darkMode()
            ->themeSwitcher()
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Asistencia'),
                NavigationGroup::make('Gestión de personal'),
                NavigationGroup::make('Organización'),
                NavigationGroup::make('Seguridad'),
            ])
            // Pedido explícito del usuario (2026-09-18): el ancho completo
            // que se validó en Calendario de turnos debe aplicar a TODOS
            // los módulos, no solo a esa página -- Filament limita
            // cualquier página a 7xl (1280px) por defecto si nada lo
            // indica distinto (index.blade.php del layout base). Puesto acá,
            // a nivel de panel, en vez de repetirlo en cada Resource/Page.
            ->maxContentWidth(Width::Full)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                // Filament recuerda el estado del sidebar en localStorage. El usuario
                // pidió explícitamente que el menú arranque SIEMPRE contraído (no solo
                // la primera vez) -- por eso se fuerza en cada carga de página, antes de
                // que Alpine inicialice el store, en vez de solo fijar un valor inicial
                // condicional. Sigue siendo expandible con un clic durante la sesión;
                // solo no se "recuerda" abierto entre recargas.
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script>
                        (() => {
                            const comprimirMenu = () => {
                                localStorage.setItem('isOpenDesktop', 'false');
                                localStorage.setItem('isOpen', 'false');
                                window.Alpine?.store('sidebar')?.close();
                            };

                            comprimirMenu();
                            document.addEventListener('alpine:init', () => queueMicrotask(comprimirMenu), { once: true });
                            document.addEventListener('livewire:navigated', comprimirMenu);
                        })();
                    </script>
                    HTML),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                ResumenOperativo::class,
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
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Seguridad')
                    ->navigationSort(2),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
