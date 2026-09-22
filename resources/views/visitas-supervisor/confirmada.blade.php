<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visita registrada</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #111827; padding: 1.5rem; text-align: center; }
        main { width: min(100%, 26rem); background: white; border-radius: 1.25rem; padding: 2.5rem 2rem; box-shadow: 0 14px 35px rgba(0,0,0,.09); }
        .ok { display: inline-grid; place-items: center; width: 4rem; height: 4rem; border-radius: 999px; background: #dcfce7; color: #15803d; font-size: 2rem; }
        h1 { margin: 1.25rem 0 .5rem; font-size: 1.4rem; }
        p { margin: .35rem 0; color: #4b5563; }
        .hora { margin-top: 1.5rem; font-size: 1.05rem; font-weight: 700; color: #166534; }
    </style>
</head>
<body>
    <main>
        <div class="ok">✓</div>
        <h1>{{ $nueva ? 'Visita registrada' : 'Visita ya registrada hoy' }}</h1>
        <p>{{ $sucursal->nombre }}</p>
        @if ($puntoVenta)<p>{{ $puntoVenta->nombre }}</p>@endif
        <p class="hora">{{ $visita->fecha_hora->format('d/m/Y H:i') }}</p>
    </main>
</body>
</html>
