<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escanea el código QR</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #fff; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.06); padding: 2rem 1.75rem; width: 100%; max-width: 22rem; text-align: center; }
        h1 { font-size: 1.1rem; margin: 0 0 0.5rem; color: #111827; }
        p { color: #6b7280; font-size: 0.875rem; line-height: 1.5; margin: 0 0 1.5rem; }
        a.salir { color: #6b7280; font-size: 0.8rem; text-decoration: none; }
        .salir-btn { display: inline-flex; align-items: center; gap: 0.35rem; }
    </style>
</head>
<body>
    <div class="card">
        <x-heroicon-o-camera style="width: 2.75rem; height: 2.75rem; color: #2563eb; margin: 0 auto 0.75rem; display: block;" />
        <h1>Hola, {{ $colaborador->nombre_completo }}</h1>
        <p>Sesión iniciada correctamente. Escanea el código QR de la pantalla de tu tienda para registrar tu entrada, salida o refrigerio.</p>

        @include('marcacion.partials.escaner')

        <a href="{{ route('horario.show') }}" class="salir salir-btn">
            <x-heroicon-o-calendar-days style="width: 1rem; height: 1rem;" />
            Ver mi horario
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="all: unset; cursor: pointer;" class="salir salir-btn">
                <x-heroicon-o-arrow-left-on-rectangle style="width: 1rem; height: 1rem;" />
                Cerrar sesión
            </button>
        </form>
    </div>
</body>
</html>
