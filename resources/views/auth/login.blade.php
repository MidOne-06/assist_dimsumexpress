<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso — {{ $apariencia->nombre() }}</title>
    <link rel="icon" href="{{ $apariencia->iconoUrl() }}">
    <x-pwa-head />
    <style>
        :root {
            color-scheme: light;
            --primary: {{ $apariencia->colorPrimario() }};
            --page: #f8fafc; --surface: rgb(255 255 255 / .96); --ink: #172033; --muted: #475467;
            --field: #fff; --field-border: #d0d5dd; --grid: color-mix(in srgb, var(--primary) 19%, transparent);
            --ring: color-mix(in srgb, var(--primary) 28%, transparent); --ring-soft: color-mix(in srgb, var(--primary) 7%, transparent); --shadow: rgb(15 23 42 / .16);
        }
        :root[data-theme="dark"] {
            color-scheme: dark;
            --page: #0f172a; --surface: rgb(24 24 27 / .96); --ink: #f8fafc; --muted: #d0d5dd;
            --field: #27272a; --field-border: #3f3f46; --grid: color-mix(in srgb, var(--primary) 36%, transparent);
            --ring: color-mix(in srgb, var(--primary) 38%, transparent); --ring-soft: color-mix(in srgb, var(--primary) 9%, transparent); --shadow: rgb(2 6 23 / .34);
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme]) {
                color-scheme: dark;
                --page: #0f172a; --surface: rgb(24 24 27 / .96); --ink: #f8fafc; --muted: #d0d5dd;
                --field: #27272a; --field-border: #3f3f46; --grid: color-mix(in srgb, var(--primary) 36%, transparent);
                --ring: color-mix(in srgb, var(--primary) 38%, transparent); --ring-soft: color-mix(in srgb, var(--primary) 9%, transparent); --shadow: rgb(2 6 23 / .34);
            }
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 1.5rem; overflow: hidden;
            background: radial-gradient(circle at 10% 15%, color-mix(in srgb, var(--primary) 20%, transparent), transparent 31rem), radial-gradient(circle at 88% 84%, color-mix(in srgb, var(--primary) 14%, transparent), transparent 28rem), var(--page);
            color: var(--ink); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        body::before, body::after { position: fixed; z-index: -1; pointer-events: none; content: ''; }
        body::before { inset: 0; opacity: .5; background-image: linear-gradient(var(--grid) 1px, transparent 1px), linear-gradient(90deg, var(--grid) 1px, transparent 1px); background-size: 3.5rem 3.5rem; mask-image: radial-gradient(ellipse at center, black, transparent 74%); }
        body::after { top: 50%; right: -13rem; width: min(40rem, 68vw); aspect-ratio: 1; border: 1px solid var(--ring); border-radius: 9999px; box-shadow: 0 0 0 4rem var(--ring-soft), 0 0 0 8rem color-mix(in srgb, var(--primary) 5%, transparent); transform: translateY(-50%); }
        .login { position: relative; width: 100%; max-width: 25rem; padding: 2rem; background: var(--surface); border: 1px solid color-mix(in srgb, var(--primary) 22%, white); border-radius: 1rem; box-shadow: 0 1.5rem 4rem var(--shadow); backdrop-filter: blur(1rem); }
        .brand { display: grid; min-height: 5.25rem; place-items: center; margin: 0 0 1.5rem; }
        .brand-logo { display: block; width: auto; height: 4.25rem; max-width: 12rem; object-fit: contain; }
        .brand-logo-dark { display: none; }
        :root[data-theme="dark"] .brand-logo-light { display: none; }
        :root[data-theme="dark"] .brand-logo-dark { display: block; }
        @media (prefers-color-scheme: dark) { :root:not([data-theme]) .brand-logo-light { display: none; } :root:not([data-theme]) .brand-logo-dark { display: block; } }
        .field { margin-bottom: 1.125rem; } label { display: block; margin-bottom: .45rem; color: var(--ink); font-size: .875rem; font-weight: 600; }
        input[type=email], input[type=password], input[type=text] { width: 100%; height: 2.875rem; padding: .65rem .75rem; border: 1px solid var(--field-border); border-radius: .625rem; background: var(--field); color: var(--ink); font: inherit; font-size: .9375rem; transition: border-color .15s ease, box-shadow .15s ease; }
        input:focus { outline: 0; border-color: var(--primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 18%, transparent); }
        .password-control { position: relative; } .password-control input { padding-right: 5.25rem; }
        .password-toggle { position: absolute; top: 50%; right: .25rem; transform: translateY(-50%); min-height: 2.25rem; padding: 0 .55rem; border: 0; border-radius: .375rem; background: transparent; color: var(--muted); font: inherit; font-size: .8125rem; font-weight: 600; cursor: pointer; }
        .password-toggle:hover, .password-toggle:focus-visible { background: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--ink); outline: 0; }
        .remember { display: flex; align-items: center; gap: .5rem; margin: .25rem 0 1.5rem; color: var(--muted); font-size: .875rem; } .remember input { width: 1rem; height: 1rem; margin: 0; accent-color: var(--primary); } .remember label { margin: 0; color: inherit; font-weight: 400; }
        .submit { display: inline-flex; align-items: center; justify-content: center; width: 100%; min-height: 2.875rem; border: 0; border-radius: .625rem; background: var(--primary); color: #fff; font: inherit; font-size: .9375rem; font-weight: 700; cursor: pointer; transition: filter .15s ease, transform .15s ease; }
        .submit:hover { filter: brightness(.94); transform: translateY(-1px); } .submit:focus-visible { outline: 3px solid color-mix(in srgb, var(--primary) 35%, transparent); outline-offset: 2px; }
        .pwa-install { display: inline-flex; width: 100%; min-height: 2.5rem; align-items: center; justify-content: center; gap: .45rem; margin-top: .75rem; border: 0; background: transparent; color: var(--muted); font: inherit; font-size: .8125rem; font-weight: 600; cursor: pointer; }
        .pwa-install:hover, .pwa-install:focus-visible { color: var(--primary); outline: 0; } .pwa-install svg { width: 1rem; height: 1rem; }
        .errors { margin-bottom: 1.25rem; padding: .75rem .875rem; border: 1px solid #fecdca; border-radius: .625rem; background: #fef3f2; color: #b42318; font-size: .875rem; line-height: 1.45; }
        @media (max-width: 640px) { body { padding: 1rem; } body::after { right: -18rem; width: 34rem; } .login { padding: 1.5rem; } }
    </style>
</head>
<body>
    <main class="login" aria-label="Acceso">
        <div class="brand">
            <img class="brand-logo brand-logo-light" src="{{ $apariencia->logoUrl() }}" alt="{{ $apariencia->nombre() }}">
            <img class="brand-logo brand-logo-dark" src="{{ $apariencia->logoOscuroUrl() }}" alt="" aria-hidden="true">
        </div>
        @if ($errors->any())
            <div class="errors" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field"><label for="email">Correo</label><input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
            <div class="field"><label for="password">Contraseña</label><div class="password-control"><input type="password" id="password" name="password" autocomplete="current-password" required><button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Mostrar contraseña" aria-pressed="false"><span data-password-toggle-label>Mostrar</span></button></div></div>
            <div class="remember"><input type="checkbox" id="recordar" name="recordar"><label for="recordar">Recordarme en este dispositivo</label></div>
            <button class="submit" type="submit">Ingresar</button>
        </form>
        <button class="pwa-install" type="button" data-pwa-install hidden><x-heroicon-o-arrow-down-tray /> Instalar aplicación</button>
    </main>
    <x-pwa-register />
    <script>
        const passwordToggle = document.querySelector('[data-password-toggle]');
        const passwordInput = document.getElementById('password');
        passwordToggle?.addEventListener('click', () => { const visible = passwordInput.type === 'password'; passwordInput.type = visible ? 'text' : 'password'; passwordToggle.setAttribute('aria-pressed', String(visible)); passwordToggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña'); passwordToggle.querySelector('[data-password-toggle-label]').textContent = visible ? 'Ocultar' : 'Mostrar'; });
    </script>
</body>
</html>
