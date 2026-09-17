<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 1.5rem; }
        .card { background: #fff; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.06); padding: 2rem 1.75rem; width: 100%; max-width: 24rem; }
        h1 { font-size: 1.15rem; margin: 0 0 0.25rem; color: #111827; }
        p.sub { margin: 0 0 1.5rem; color: #6b7280; font-size: 0.875rem; }
        label { display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 0.35rem; }
        input[type=email], input[type=password] { width: 100%; padding: 0.65rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; margin-bottom: 1rem; }
        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }
        button { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.75rem; background: #2563eb; color: #fff; border: none; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .errores { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.65rem 0.85rem; border-radius: 0.5rem; font-size: 0.8rem; margin-bottom: 1rem; }
        .check { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 1.25rem; font-size: 0.8rem; color: #4b5563; }
    </style>
</head>
<body>
    <div class="card">
        <x-heroicon-o-finger-print style="width: 2.25rem; height: 2.25rem; color: #2563eb; margin-bottom: 0.5rem;" />
        <h1>{{ config('app.name') }}</h1>
        <p class="sub">Ingresa con tu correo y contraseña para marcar tu asistencia.</p>

        @if ($errors->any())
            <div class="errores">
                @foreach ($errors->all() as $error)
                    {{ $error }}<br>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>

            <div class="check">
                <input type="checkbox" id="recordar" name="recordar" style="width:auto;margin:0;">
                <label for="recordar" style="margin:0;font-weight:400;">Recordarme en este dispositivo</label>
            </div>

            <button type="submit">
                <x-heroicon-o-arrow-right-on-rectangle style="width: 1.1rem; height: 1.1rem;" />
                Ingresar
            </button>
        </form>
    </div>
</body>
</html>
