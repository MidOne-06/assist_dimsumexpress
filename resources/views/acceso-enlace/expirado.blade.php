<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enlace no disponible — {{ $apariencia->nombre() }}</title>
    <link rel="icon" href="{{ $apariencia->iconoUrl() }}">
    <x-pwa-head />
    <script>
        (() => {
            const preference = window.matchMedia('(prefers-color-scheme: dark)');
            const syncTheme = () => document.documentElement.classList.toggle('dark', preference.matches);
            syncTheme();
            preference.addEventListener?.('change', syncTheme);
        })();
    </script>
    @filamentStyles
    {{ filament()->getTheme()->getHtml() }}
    {{ filament()->getFontPreloadHtml() }}
    {{ filament()->getFontHtml() }}
    <style>
        .access-page { min-height: 100vh; margin: 0; background: #f8fafc; color: #111827; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .access-shell { display: flex; box-sizing: border-box; min-height: 100vh; width: 100%; max-width: 32rem; margin: 0 auto; padding: 1rem; align-items: center; }
        .access-card.fi-section { width: 100%; }
        .access-header { display: flex; min-height: 3.5rem; margin-bottom: 2rem; align-items: center; justify-content: center; }
        .access-brand { display: flex; width: 100%; min-height: 3.5rem; align-items: center; justify-content: center; }
        .access-logo { display: block; width: auto; height: 3.5rem; max-width: 12rem; object-fit: contain; }
        .access-logo-dark { display: none; }
        .access-empty { display: flex; flex-direction: column; align-items: center; text-align: center; }
        .access-empty-icon { display: flex; width: 3rem; height: 3rem; margin-bottom: 1rem; align-items: center; justify-content: center; border-radius: 9999px; background: var(--danger-50); color: var(--danger-600); }
        .access-empty-icon svg { width: 1.5rem; height: 1.5rem; }
        .access-title { margin: 0; font-size: 1.125rem; font-weight: 600; line-height: 1.5rem; }
        .access-message { margin: .5rem 0 0; color: #6b7280; font-size: .875rem; line-height: 1.5rem; }
        html.dark .access-page { background: #030712; color: #f9fafb; }
        html.dark .access-card.fi-section { border-color: #374151; background: #111827; }
        html.dark .access-logo-light { display: none; }
        html.dark .access-logo-dark { display: block; }
        html.dark .access-empty-icon { background: color-mix(in srgb, var(--danger-500) 12%, transparent); color: var(--danger-400); }
        html.dark .access-message { color: #9ca3af; }
        @media (min-width: 640px) { .access-shell { padding: 1.5rem; } }
    </style>
</head>
<body class="access-page">
    <main class="access-shell">
        <x-filament::section class="access-card" compact>
            <div class="access-header">
                <div class="access-brand">
                    <img class="access-logo access-logo-light" src="{{ $apariencia->logoUrl() }}" alt="{{ $apariencia->nombre() }}">
                    <img class="access-logo access-logo-dark" src="{{ $apariencia->logoOscuroUrl() }}" alt="{{ $apariencia->nombre() }}">
                </div>
            </div>
            <div class="access-empty">
                <div class="access-empty-icon"><x-heroicon-o-exclamation-triangle /></div>
                <h1 class="access-title">Enlace no disponible</h1>
                <p class="access-message">Solicita un nuevo enlace de acceso.</p>
            </div>
        </x-filament::section>
    </main>
    @filamentScripts
    <x-pwa-register />
</body>
</html>
