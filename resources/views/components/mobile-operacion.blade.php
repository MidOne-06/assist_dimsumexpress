@props([
    'title',
    'section' => null,
    'appearance' => null,
])

@php($appearance ??= app(\App\Services\AparienciaSistemaService::class))
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }} — {{ $appearance->nombre() }}</title>
    <link rel="icon" href="{{ $appearance->iconoUrl() }}">
    <x-pwa-head />
    <x-public-theme />
    <style>
        :root { --mo-primary: {{ $appearance->colorPrimario() }}; }
        * { box-sizing: border-box; }
        .mo-body { min-height: 100dvh; margin: 0; display: grid; place-items: center; padding: 1rem; background: var(--app-page); color: var(--app-text); font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        .mo-shell { width: min(100%, 30rem); padding: 1.25rem; background: var(--app-surface); border: 1px solid var(--app-border); border-radius: 1rem; box-shadow: 0 12px 28px rgb(16 24 40 / .08); }
        .mo-brand { display: grid; justify-items: center; gap: .4rem; padding-bottom: 1rem; border-bottom: 1px solid var(--app-border); }
        .mo-brand img { width: auto; height: 3rem; max-width: 10rem; object-fit: contain; }
        .mo-brand__section { color: var(--app-muted); font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .mo-content { padding-top: 1rem; }
        .mo-heading { margin: 0; font-size: 1.25rem; line-height: 1.3; letter-spacing: -.01em; }
        .mo-subheading { margin: .3rem 0 0; color: var(--app-muted); font-size: .875rem; line-height: 1.45; }
        .mo-callout { display: flex; gap: .65rem; margin-top: 1rem; padding: .8rem .875rem; border: 1px solid var(--app-border); border-radius: .75rem; background: var(--app-subtle); font-size: .875rem; line-height: 1.4; }
        .mo-callout svg { width: 1.25rem; height: 1.25rem; flex: 0 0 auto; }
        .mo-callout--success { color: var(--app-success); background: var(--app-success-bg); border-color: color-mix(in srgb, var(--app-success) 38%, var(--app-border)); }
        .mo-callout--warning { color: var(--app-warning); background: var(--app-warning-bg); border-color: color-mix(in srgb, var(--app-warning) 38%, var(--app-border)); }
        .mo-callout--danger { color: var(--app-danger); background: var(--app-danger-bg); border-color: color-mix(in srgb, var(--app-danger) 38%, var(--app-border)); }
        .mo-actions { display: grid; gap: .625rem; margin-top: 1rem; }
        .mo-action { display: flex; align-items: center; gap: .7rem; width: 100%; min-height: 3.125rem; padding: .7rem .8rem; border: 1px solid var(--app-border); border-radius: .75rem; background: var(--app-surface); color: var(--app-text); font: inherit; font-size: .9375rem; font-weight: 700; text-align: left; cursor: pointer; }
        .mo-action svg { width: 1.2rem; height: 1.2rem; flex: 0 0 auto; }
        .mo-action--success:not(:disabled) { color: var(--app-success); border-color: color-mix(in srgb, var(--app-success) 45%, var(--app-border)); }
        .mo-action--warning:not(:disabled) { color: var(--app-warning); border-color: color-mix(in srgb, var(--app-warning) 45%, var(--app-border)); }
        .mo-action--info:not(:disabled) { color: var(--app-info); border-color: color-mix(in srgb, var(--app-info) 45%, var(--app-border)); }
        .mo-action--danger:not(:disabled) { color: var(--app-danger); border-color: color-mix(in srgb, var(--app-danger) 45%, var(--app-border)); }
        .mo-action:disabled { opacity: .55; cursor: not-allowed; }
        .mo-action__meta { display: grid; gap: .15rem; }
        .mo-action__hint { display: block; margin: .15rem 0 0 1.9rem; color: var(--app-muted); font-size: .75rem; line-height: 1.35; }
        .mo-primary-button { display: inline-flex; min-height: 2.875rem; width: 100%; align-items: center; justify-content: center; gap: .45rem; border: 0; border-radius: .75rem; background: var(--mo-primary); color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .mo-primary-button svg { width: 1.15rem; height: 1.15rem; }
        .mo-linkbar { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1rem; }
        .mo-linkbar a, .mo-linkbar button { display: inline-flex; align-items: center; gap: .35rem; min-height: 2.25rem; border: 0; background: transparent; padding: 0; color: var(--app-muted); font: inherit; font-size: .8125rem; text-decoration: none; cursor: pointer; }
        .mo-linkbar svg { width: 1rem; height: 1rem; }
        .mo-status { display: grid; justify-items: center; text-align: center; padding: .75rem 0 .25rem; }
        .mo-status__icon { width: 3rem; height: 3rem; margin-bottom: .875rem; }
        .mo-status__time { margin-top: .75rem; color: var(--app-success); font-size: 1.75rem; font-weight: 700; }
        .mo-status__detail { margin: .25rem 0 0; color: var(--app-muted); font-size: .875rem; line-height: 1.45; }
        @media (max-width: 640px) {
            .mo-body { display: block; padding: 0; }
            .mo-shell { min-height: 100dvh; width: 100%; padding: max(1.25rem, env(safe-area-inset-top)) 1rem calc(1.25rem + env(safe-area-inset-bottom)); border: 0; border-radius: 0; box-shadow: none; }
        }
    </style>
</head>
<body class="mo-body">
    <main class="mo-shell">
        <header class="mo-brand">
            <img src="{{ $appearance->logoUrl() }}" alt="{{ $appearance->nombre() }}">
            @if ($section)<span class="mo-brand__section">{{ $section }}</span>@endif
        </header>
        <div class="mo-content">{{ $slot }}</div>
    </main>
</body>
</html>
