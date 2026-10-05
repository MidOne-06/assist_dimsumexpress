<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visita registrada</title>
    <x-public-theme />
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background:var(--app-page); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color:var(--app-text); padding: 1.5rem; text-align: center; }
        main { width: min(100%, 26rem); background:var(--app-surface); border:1px solid var(--app-border); border-radius: 1.25rem; padding: 2.5rem 2rem; }
        .ok { display: inline-grid; place-items: center; width: 4rem; height: 4rem; border-radius: 999px; background:var(--app-success-bg); color:var(--app-success); }
        .ok svg { width:2.25rem; height:2.25rem; }
        h1 { margin: 1.25rem 0 .5rem; font-size: 1.4rem; }
        p { margin: .35rem 0; color:var(--app-muted); }
        .hora { margin-top: 1.5rem; font-size: 1.05rem; font-weight: 700; color:var(--app-success); }
    </style>
</head>
<body>
    <main>
        <div class="ok"><x-heroicon-s-check-circle /></div>
        <h1>{{ $nueva ? 'Visita registrada' : 'Visita ya registrada hoy' }}</h1>
        <p>{{ $sucursal->nombre }}</p>
        @if ($puntoVenta)<p>{{ $puntoVenta->nombre }}</p>@endif
        <p class="hora">{{ $visita->fecha_hora->format('d/m/Y H:i') }}</p>
    </main>
</body>
</html>
