<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activar acceso — {{ $apariencia->nombre() }}</title>
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
        .access-shell { display: flex; box-sizing: border-box; min-height: 100vh; width: 100%; max-width: 29rem; margin: 0 auto; padding: 1rem; align-items: center; }
        .access-card.fi-section { width: 100%; }
        .access-card .fi-section-content { padding: 1.25rem; }
        .access-header { display: flex; min-height: 3rem; margin-bottom: 1rem; align-items: center; justify-content: center; }
        .access-brand { display: flex; width: 100%; min-height: 3rem; align-items: center; justify-content: center; }
        .access-logo { display: block; width: auto; height: 3rem; max-width: 10rem; object-fit: contain; }
        .access-logo-dark { display: none; }
        .access-summary, .access-unavailable { display: flex; margin-bottom: 1.125rem; align-items: center; gap: .75rem; }
        .access-summary-icon { display: flex; width: 2.25rem; height: 2.25rem; flex: 0 0 2.25rem; align-items: center; justify-content: center; border-radius: .625rem; background: var(--primary-50); color: var(--primary-600); }
        .access-summary-icon svg { width: 1.125rem; height: 1.125rem; }
        .access-title { margin: 0; font-size: 1.0625rem; font-weight: 600; line-height: 1.35rem; }
        .access-subtitle { margin: .25rem 0 0; color: #6b7280; font-size: .875rem; line-height: 1.25rem; }
        .access-form { display: grid; gap: .875rem; }
        .access-form .fi-fo-field-wrp { margin: 0; }
        .access-form .fi-fo-field-wrp-label { padding-bottom: .25rem; }
        .access-password-policy { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .375rem .875rem; margin: -.125rem 0 0; padding: .75rem; border: 1px solid #e5e7eb; border-radius: .625rem; list-style: none; color: #6b7280; font-size: .75rem; }
        .access-password-policy li { display: flex; gap: .375rem; align-items: center; }
        .access-rule-icon { position: relative; display: inline-flex; width: 1rem; height: 1rem; flex: 0 0 1rem; }
        .access-rule-icon svg { position: absolute; inset: 0; width: 1rem; height: 1rem; }
        .access-rule-valid { display: none; }
        .access-password-policy li.is-valid { color: var(--success-600); }
        .access-password-policy li.is-valid .access-rule-pending { display: none; }
        .access-password-policy li.is-valid .access-rule-valid { display: block; }
        .access-submit { width: 100%; justify-content: center; }
        .access-expiry { margin: 1.25rem 0 0; color: #6b7280; font-size: .75rem; text-align: center; }
        html.dark .access-page { background: #030712; color: #f9fafb; }
        html.dark .access-card.fi-section { border-color: #374151; background: #111827; }
        html.dark .access-logo-light { display: none; }
        html.dark .access-logo-dark { display: block; }
        html.dark .access-summary-icon { background: color-mix(in srgb, var(--primary-400) 12%, transparent); color: var(--primary-400); }
        html.dark .access-subtitle, html.dark .access-expiry { color: #9ca3af; }
        html.dark .access-password-policy { color: #9ca3af; }
        html.dark .access-password-policy { border-color: #374151; }
        html.dark .access-password-policy li.is-valid { color: var(--success-400); }
        @media (min-width: 640px) { .access-shell { max-width: 30rem; padding: 1.5rem; } .access-card .fi-section-content { padding: 1.5rem; } }
    </style>
</head>
<body class="access-page">
    <main class="access-shell">
        <livewire:activar-acceso-colaborador :token="$token" />
    </main>
    @filamentScripts
    <x-pwa-register />
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const password = document.getElementById('access-password');
            const confirmation = document.getElementById('access-password-confirmation');
            const policy = document.querySelector('[data-password-policy]');

            if (! password || ! confirmation || ! policy) return;

            const rules = {
                length: (value) => value.length >= 8,
                upper: (value) => /[A-Z]/.test(value),
                lower: (value) => /[a-z]/.test(value),
                number: (value) => /\d/.test(value),
                match: (value) => value.length > 0 && value === confirmation.value,
            };

            const refresh = () => Object.entries(rules).forEach(([name, passes]) => {
                const item = policy.querySelector(`[data-rule="${name}"]`);
                const valid = passes(password.value);
                item.classList.toggle('is-valid', valid);
            });

            password.addEventListener('input', refresh);
            confirmation.addEventListener('input', refresh);
            refresh();
        });
    </script>
</body>
</html>
