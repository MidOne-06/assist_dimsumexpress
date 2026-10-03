<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marcación registrada</title>
    <x-public-theme />
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:var(--app-page); color:var(--app-text); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; display:flex; align-items:center; justify-content:center; padding:1.25rem; }
        .card { background:var(--app-surface); border:1px solid var(--app-border); border-radius:1rem; padding:1.8rem 1.5rem; width:100%; max-width:25rem; text-align:center; }
        h1 { font-size:1.18rem; margin:0 0 .3rem; color:var(--app-text); } .hora { font-size:2rem; font-weight:700; color:var(--app-success); margin:.7rem 0; } .fecha { color:var(--app-muted); font-size:.84rem; margin:0 0 1rem; }
        .siguiente { text-align:left; background:var(--app-info-bg); border:1px solid color-mix(in srgb,var(--app-info) 38%,var(--app-border)); border-radius:.7rem; padding:.85rem .9rem; color:var(--app-muted); font-size:.83rem; line-height:1.45; margin:0 0 1rem; }
        .siguiente strong { display:block; color:var(--app-info); font-size:.9rem; margin-bottom:.16rem; }
        .acciones { display:flex; justify-content:center; gap:1rem; flex-wrap:wrap; } a, button { color:var(--app-info); font-size:.84rem; text-decoration:none; font-weight:600; background:none; border:0; padding:0; cursor:pointer; } .accion { display:inline-flex; align-items:center; gap:.32rem; }
    </style>
</head>
<body>
    @php
        $etiquetas = ['entrada' => 'Ingreso de turno registrado', 'salida' => 'Salida de turno registrada', 'salida_refrigerio' => 'Salida de refrigerio registrada', 'regreso_refrigerio' => 'Ingreso de refrigerio registrado'];
        $siguiente = match ($marcacion->tipo) {
            'entrada' => ['Listo', 'Cuando necesites marcar nuevamente, selecciona la acción correspondiente y escanea el QR.'],
            'salida_refrigerio' => ['Listo', 'Cuando regreses, selecciona “Ingreso de refrigerio” y escanea el QR.'],
            'regreso_refrigerio' => ['Listo', 'Continúa con tu jornada. Cuando corresponda, registra tu salida.'],
            'salida' => ['Listo', 'Tu salida quedó registrada.'],
        };
    @endphp
    <div class="card">
        <x-heroicon-s-check-circle style="width:2.75rem;height:2.75rem;color:#16a34a;margin:0 auto .75rem;display:block;" />
        <h1>{{ $etiquetas[$marcacion->tipo] }}</h1>
        <div class="hora">{{ $marcacion->fecha_hora->format('H:i:s') }}</div>
        <p class="fecha">{{ $marcacion->fecha_hora->translatedFormat('l d \d\e F') }}</p>
        <div class="siguiente"><strong>{{ $siguiente[0] }}</strong>{{ $siguiente[1] }}</div>
        <div class="acciones">
            <a href="{{ route('marcacion.show') }}" class="accion"><x-heroicon-o-camera style="width:1rem;height:1rem;" /> Volver a marcar</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="accion"><x-heroicon-o-arrow-left-on-rectangle style="width:1rem;height:1rem;" /> Cerrar sesión</button></form>
        </div>
    </div>
</body>
</html>
