<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso — {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 1.5rem; background: #f4f6f8; color: #172033; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .login { width: 100%; max-width: 25rem; padding: 2.25rem; background: #fff; border: 1px solid #e4e7ec; border-radius: .875rem; box-shadow: 0 12px 28px rgba(16, 24, 40, .08); }
        .brand { display: flex; align-items: center; gap: .625rem; margin-bottom: 2rem; color: #16243c; font-size: 1.05rem; font-weight: 700; letter-spacing: -.015em; }
        .brand-mark { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: .55rem; background: #16243c; color: #fff; }
        h1 { margin: 0 0 1.5rem; color: #172033; font-size: 1.375rem; line-height: 1.25; letter-spacing: -.025em; }
        .field { margin-bottom: 1.125rem; }
        label { display: block; margin-bottom: .45rem; color: #344054; font-size: .875rem; font-weight: 600; }
        input[type=email], input[type=password], input[type=text] { width: 100%; height: 2.75rem; padding: .65rem .75rem; border: 1px solid #cfd4dc; border-radius: .5rem; background: #fff; color: #172033; font-size: .9375rem; transition: border-color .15s ease, box-shadow .15s ease; }
        input:focus { outline: 0; border-color: #b7791f; box-shadow: 0 0 0 3px rgba(183, 121, 31, .14); }
        .password-control { position: relative; }
        .password-control input { padding-right: 5.25rem; }
        .password-toggle { position: absolute; top: 50%; right: .25rem; transform: translateY(-50%); min-height: 2.25rem; padding: 0 .55rem; border: 0; border-radius: .375rem; background: transparent; color: #475467; font: inherit; font-size: .8125rem; font-weight: 600; cursor: pointer; }
        .password-toggle:hover, .password-toggle:focus-visible { background: #f2f4f7; color: #172033; outline: 0; }
        .remember { display: flex; align-items: center; gap: .5rem; margin: .25rem 0 1.5rem; color: #475467; font-size: .875rem; }
        .remember input { width: 1rem; height: 1rem; margin: 0; accent-color: #b7791f; }
        .remember label { margin: 0; color: inherit; font-weight: 400; }
        .submit { display: inline-flex; align-items: center; justify-content: center; width: 100%; min-height: 2.875rem; border: 0; border-radius: .5rem; background: #16243c; color: #fff; font: inherit; font-size: .9375rem; font-weight: 600; cursor: pointer; transition: background .15s ease; }
        .submit:hover { background: #243858; }
        .submit:focus-visible { outline: 3px solid rgba(183, 121, 31, .35); outline-offset: 2px; }
        .errors { margin-bottom: 1.25rem; padding: .75rem .875rem; border: 1px solid #fecdca; border-radius: .5rem; background: #fef3f2; color: #b42318; font-size: .875rem; line-height: 1.45; }
        @media (max-width: 420px) { body { padding: 1rem; } .login { padding: 1.5rem; } }
    </style>
</head>
<body>
    <main class="login" aria-labelledby="titulo-acceso">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M5 8h14M5 16h14" /></svg>
            </span>
            <span>{{ config('app.name') }}</span>
        </div>

        <h1 id="titulo-acceso">Acceso de colaborador</h1>

        @if ($errors->any())
            <div class="errors" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Correo</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </div>

            <div class="field">
                <label for="password">Contraseña</label>
                <div class="password-control">
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                    <button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Mostrar contraseña" aria-pressed="false">
                        <span data-password-toggle-label>Mostrar</span>
                    </button>
                </div>
            </div>

            <div class="remember">
                <input type="checkbox" id="recordar" name="recordar">
                <label for="recordar">Recordarme en este dispositivo</label>
            </div>

            <button class="submit" type="submit">Ingresar</button>
        </form>
    </main>

    <script>
        const passwordToggle = document.querySelector('[data-password-toggle]');
        const passwordInput = document.getElementById('password');

        passwordToggle?.addEventListener('click', () => {
            const visible = passwordInput.type === 'password';
            passwordInput.type = visible ? 'text' : 'password';
            passwordToggle.setAttribute('aria-pressed', String(visible));
            passwordToggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
            passwordToggle.querySelector('[data-password-toggle-label]').textContent = visible ? 'Ocultar' : 'Mostrar';
        });
    </script>
</body>
</html>
