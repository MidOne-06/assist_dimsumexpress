<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Services\AparienciaSistemaService;
use App\Filament\Widgets\MarcacionesPorHoraChart;
use App\Filament\Widgets\ResumenOperativo;
use App\Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
            ->brandName(fn (): string => app(AparienciaSistemaService::class)->nombre())
            ->brandLogo(fn (): string => app(AparienciaSistemaService::class)->logoUrl())
            ->darkModeBrandLogo(fn (): string => app(AparienciaSistemaService::class)->logoOscuroUrl())
            ->brandLogoHeight('2rem')
            ->favicon(fn (): string => app(AparienciaSistemaService::class)->iconoUrl())
            ->revealablePasswords()
            ->colors(fn (): array => [
                'primary' => Color::hex(app(AparienciaSistemaService::class)->colorPrimario()),
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
                NavigationGroup::make('Configuración'),
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
                fn (): HtmlString => (function (): HtmlString {
                    // Se evalúa al renderizar, no al registrar el panel. Así
                    // Artisan y las migraciones no dependen de esta tabla.
                    $colorPrimario = app(AparienciaSistemaService::class)->colorPrimario();

                    return new HtmlString(<<<HTML
                    <style>
                        .fi-simple-layout {
                            --asistencia-acento: {$colorPrimario};
                            position: relative;
                            isolation: isolate;
                            overflow: hidden;
                            background:
                                radial-gradient(circle at 12% 16%, color-mix(in srgb, var(--asistencia-acento) 28%, transparent), transparent 32rem),
                                radial-gradient(circle at 88% 84%, color-mix(in srgb, var(--asistencia-acento) 18%, transparent), transparent 30rem),
                                #0f172a;
                        }

                        .fi-simple-layout::before,
                        .fi-simple-layout::after {
                            position: fixed;
                            z-index: -1;
                            pointer-events: none;
                            content: '';
                        }

                        .fi-simple-layout::before {
                            inset: 0;
                            opacity: .2;
                            background-image:
                                linear-gradient(color-mix(in srgb, var(--asistencia-acento) 40%, transparent) 1px, transparent 1px),
                                linear-gradient(90deg, color-mix(in srgb, var(--asistencia-acento) 40%, transparent) 1px, transparent 1px);
                            background-size: 3.5rem 3.5rem;
                            mask-image: radial-gradient(ellipse at center, black, transparent 72%);
                        }

                        .fi-simple-layout::after {
                            top: 50%;
                            right: -12rem;
                            width: min(42rem, 65vw);
                            aspect-ratio: 1;
                            border: 1px solid color-mix(in srgb, var(--asistencia-acento) 42%, transparent);
                            border-radius: 9999px;
                            box-shadow:
                                0 0 0 4rem color-mix(in srgb, var(--asistencia-acento) 9%, transparent),
                                0 0 0 8rem color-mix(in srgb, var(--asistencia-acento) 6%, transparent);
                            transform: translateY(-50%);
                        }

                        .fi-simple-main {
                            position: relative;
                            z-index: 1;
                            border: 1px solid color-mix(in srgb, var(--asistencia-acento) 20%, white);
                            box-shadow: 0 1.5rem 4rem rgb(2 6 23 / .32);
                        }

                        .fi-simple-header .fi-logo {
                            height: 4.5rem !important;
                            max-width: 14rem;
                            object-fit: contain;
                        }

                        /* Filament elimina la clase .dark al seleccionar el
                           tema claro. El lienzo completo también debe cambiar,
                           no solamente el formulario. */
                        html:not(.dark) .fi-simple-layout {
                            background:
                                radial-gradient(circle at 12% 16%, color-mix(in srgb, var(--asistencia-acento) 20%, transparent), transparent 32rem),
                                radial-gradient(circle at 88% 84%, color-mix(in srgb, var(--asistencia-acento) 14%, transparent), transparent 30rem),
                                #f8fafc;
                        }

                        html:not(.dark) .fi-simple-layout::before {
                            opacity: .48;
                            background-image:
                                linear-gradient(color-mix(in srgb, var(--asistencia-acento) 22%, transparent) 1px, transparent 1px),
                                linear-gradient(90deg, color-mix(in srgb, var(--asistencia-acento) 22%, transparent) 1px, transparent 1px);
                        }

                        html:not(.dark) .fi-simple-layout::after {
                            border-color: color-mix(in srgb, var(--asistencia-acento) 30%, transparent);
                            box-shadow:
                                0 0 0 4rem color-mix(in srgb, var(--asistencia-acento) 8%, transparent),
                                0 0 0 8rem color-mix(in srgb, var(--asistencia-acento) 5%, transparent);
                        }

                        html:not(.dark) .fi-simple-main {
                            background: rgb(255 255 255 / .96);
                            border-color: color-mix(in srgb, var(--asistencia-acento) 24%, white);
                            box-shadow: 0 1.5rem 4rem rgb(15 23 42 / .16);
                        }

                        @media (max-width: 640px) {
                            .fi-simple-layout::after { right: -18rem; width: 34rem; }
                            .fi-simple-main { margin-block: 1.5rem; }
                        }
                    </style>
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
                    HTML);
                })(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                ResumenOperativo::class,
                MarcacionesPorHoraChart::class,
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
