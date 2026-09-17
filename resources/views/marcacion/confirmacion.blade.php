<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marcación registrada</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #fff; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.06); padding: 2rem 1.75rem; width: 100%; max-width: 22rem; text-align: center; }
        .icono { font-size: 2.75rem; margin-bottom: 0.75rem; }
        h1 { font-size: 1.15rem; margin: 0 0 0.35rem; color: #111827; }
        .hora { font-size: 2rem; font-weight: 700; color: #16a34a; margin: 0.75rem 0; }
        p.sub { color: #6b7280; font-size: 0.875rem; margin: 0 0 1.5rem; }
        a.volver { color: #2563eb; font-size: 0.85rem; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icono">✅</div>
        @php
            $etiquetas = [
                'entrada' => 'Entrada registrada',
                'salida' => 'Salida registrada',
                'salida_refrigerio' => 'Salida a refrigerio registrada',
                'regreso_refrigerio' => 'Regreso de refrigerio registrado',
            ];
        @endphp
        <h1>{{ $etiquetas[$marcacion->tipo] }}</h1>
        <div class="hora">{{ $marcacion->fecha_hora->format('H:i') }}</div>
        <p class="sub">{{ $marcacion->fecha_hora->translatedFormat('l d \d\e F') }}</p>
        <p style="color:#9ca3af;font-size:0.75rem;">Ya puedes cerrar esta pantalla.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="all: unset; cursor: pointer;" class="volver">Cerrar sesión</button>
        </form>
    </div>
</body>
</html>
