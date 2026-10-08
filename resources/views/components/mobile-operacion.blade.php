@props([
    'title',
    'section' => null,
    'appearance' => null,
    'layout' => null,
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
        :root {
            --mo-primary: {{ $appearance->colorPrimario() }};
            --mo-marking-image: url('{{ asset('images/marcacion-dimsum.png') }}');
            --mo-marking-overlay: rgb(5 18 34 / .54);
            --mo-marking-glass: rgb(255 255 255 / .86);
            --mo-marking-glass-border: rgb(255 255 255 / .72);
            --mo-marking-text: #172033;
            --mo-marking-muted: #475467;
        }
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

        /* Pantalla inicial de marcación: mantiene la operación sencilla, pero
           da prioridad al escaneo y a la identidad de la marca en móvil. */
        .mo-body--marking-home {
            min-height: 100dvh;
            background-image: linear-gradient(var(--mo-marking-overlay), var(--mo-marking-overlay)), var(--mo-marking-image);
            background-position: 40% center;
            background-size: cover;
            background-repeat: no-repeat;
        }
        .mo-body--marking-home .mo-shell {
            width: min(100%, 32rem);
            padding: clamp(1.5rem, 5vw, 2.5rem) clamp(1rem, 5vw, 2rem);
            background: transparent;
            border: 0;
            border-radius: 0;
            box-shadow: none;
        }
        .mo-body--marking-home .mo-brand {
            gap: 1.25rem;
            padding: 0 0 clamp(1.5rem, 5vw, 2.25rem);
            border: 0;
        }
        .mo-body--marking-home .mo-brand img {
            width: min(13rem, 52vw);
            height: auto;
            max-width: none;
            max-height: 7rem;
        }
        .mo-body--marking-home .mo-brand__section {
            display: flex;
            width: 100%;
            align-items: center;
            gap: .9rem;
            color: #d9e7ff;
            font-size: clamp(.85rem, 3.8vw, 1.15rem);
            font-weight: 700;
            letter-spacing: .28em;
            line-height: 1;
            text-align: center;
        }
        .mo-body--marking-home .mo-brand__section::before,
        .mo-body--marking-home .mo-brand__section::after {
            height: 1px;
            flex: 1;
            background: #e11d48;
            content: '';
        }
        .mo-body--marking-home .mo-content { padding-top: 0; }
        .mo-marking-welcome {
            padding: clamp(1.5rem, 7vw, 2.5rem);
            border: 1px solid var(--mo-marking-glass-border);
            border-radius: 1.5rem;
            background: var(--mo-marking-glass);
            box-shadow: 0 1.25rem 3.5rem rgb(2 12 27 / .2);
            color: var(--mo-marking-text);
            backdrop-filter: blur(1rem);
            -webkit-backdrop-filter: blur(1rem);
        }
        .mo-marking-greeting { margin: 0; font-size: clamp(2.25rem, 10vw, 3.5rem); font-weight: 400; letter-spacing: -.04em; line-height: .95; }
        .mo-marking-name { margin: .35rem 0 0; font-size: clamp(2.75rem, 13vw, 4.5rem); font-weight: 750; letter-spacing: -.065em; line-height: .98; }
        .mo-marking-welcome .mp-escaner { margin-top: clamp(1.5rem, 8vw, 2.5rem); }
        .mo-marking-welcome .mp-escanear {
            min-height: clamp(4.5rem, 17vw, 5.75rem);
            margin-top: 0;
            padding: .9rem 1.25rem;
            border: 1px solid rgb(255 255 255 / .45);
            border-radius: 1.25rem;
            background: #1677ff;
            box-shadow: 0 .8rem 1.5rem rgb(22 119 255 / .27);
            color: #fff;
            font-size: clamp(1.25rem, 6vw, 2rem);
            font-weight: 750;
        }
        .mo-marking-welcome .mp-escanear::after { margin-left: auto; content: '›'; font-size: 2.5rem; font-weight: 300; line-height: .5; }
        .mo-marking-welcome .mp-escanear svg { width: clamp(1.65rem, 7vw, 2.3rem) !important; height: clamp(1.65rem, 7vw, 2.3rem) !important; }
        .mo-marking-quick-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .9rem; margin-top: .9rem; }
        .mo-marking-quick-action {
            display: flex;
            min-height: 6.5rem;
            align-items: center;
            gap: .5rem;
            padding: .75rem;
            border: 1px solid rgb(148 196 255 / .48);
            border-radius: 1.2rem;
            background: rgb(7 25 48 / .7);
            box-shadow: 0 .75rem 1.75rem rgb(2 12 27 / .14);
            color: #fff;
            font-size: clamp(.875rem, 4vw, 1.05rem);
            font-weight: 700;
            line-height: 1.15;
            text-decoration: none;
            backdrop-filter: blur(.75rem);
            -webkit-backdrop-filter: blur(.75rem);
        }
        .mo-marking-quick-action button { width: 100%; border: 0; background: transparent; color: inherit; font: inherit; text-align: left; cursor: pointer; }
        .mo-marking-quick-action__icon { display: inline-flex; width: 2.4rem; height: 2.4rem; flex: 0 0 auto; align-items: center; justify-content: center; border-radius: 999px; background: rgb(147 197 253 / .16); color: #dbeafe; }
        .mo-marking-quick-action__icon svg { width: 1.3rem; height: 1.3rem; }
        .mo-marking-quick-action > span:not(.mo-marking-quick-action__icon),
        .mo-marking-quick-action--logout button > span:not(.mo-marking-quick-action__icon) { white-space: nowrap; }
        .mo-marking-quick-action > svg { width: 1.15rem; height: 1.15rem; margin-left: auto; color: #bfdbfe; }
        .mo-marking-quick-action form { width: 100%; }
        .mo-marking-quick-action--logout { cursor: pointer; }
        .mo-marking-quick-action--logout button { display: flex; min-height: 5rem; align-items: center; gap: .5rem; }
        .mo-marking-quick-action--logout button > svg { width: 1.15rem; height: 1.15rem; margin-left: auto; color: #bfdbfe; }
        @media (prefers-color-scheme: dark) {
            :root {
                --mo-marking-overlay: rgb(3 15 30 / .66);
                --mo-marking-glass: rgb(9 27 51 / .74);
                --mo-marking-glass-border: rgb(148 196 255 / .55);
                --mo-marking-text: #f8fafc;
                --mo-marking-muted: #dbeafe;
            }
        }
        @media (max-width: 640px) {
            .mo-body { display: block; padding: 0; }
            .mo-shell { min-height: 100dvh; width: 100%; padding: max(1.25rem, env(safe-area-inset-top)) 1rem calc(1.25rem + env(safe-area-inset-bottom)); border: 0; border-radius: 0; box-shadow: none; }
            .mo-body--marking-home .mo-shell { min-height: 100dvh; padding-top: max(1.5rem, env(safe-area-inset-top)); padding-bottom: max(1.5rem, env(safe-area-inset-bottom)); }
        }
        @media (max-width: 22rem) {
            .mo-marking-quick-actions { grid-template-columns: 1fr; }
            .mo-marking-quick-action { min-height: 4.5rem; }
        }
    </style>
</head>
<body @class(['mo-body', 'mo-body--' . $layout => filled($layout)])>
    <main class="mo-shell">
        <header class="mo-brand">
            <img src="{{ $appearance->logoUrl() }}" alt="{{ $appearance->nombre() }}">
            @if ($section)<span class="mo-brand__section">{{ $section }}</span>@endif
        </header>
        <div class="mo-content">{{ $slot }}</div>
    </main>
</body>
</html>
