<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marcación registrada</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; display:flex; align-items:center; justify-content:center; padding:1.25rem; }
        .card { background:#fff; border-radius:1rem; box-shadow:0 10px 25px rgba(0,0,0,.06); padding:1.8rem 1.5rem; width:100%; max-width:25rem; text-align:center; }
        h1 { font-size:1.18rem; margin:0 0 .3rem; color:#111827; } .hora { font-size:2rem; font-weight:700; color:#16a34a; margin:.7rem 0; } .fecha { color:#6b7280; font-size:.84rem; margin:0 0 1rem; }
        .siguiente { text-align:left; background:#eff6ff; border:1px solid #bfdbfe; border-radius:.7rem; padding:.85rem .9rem; color:#475569; font-size:.83rem; line-height:1.45; margin:0 0 1rem; }
        .siguiente strong { display:block; color:#1e3a8a; font-size:.9rem; margin-bottom:.16rem; }
        .acciones { display:flex; justify-content:center; gap:1rem; flex-wrap:wrap; } a, button { color:#2563eb; font-size:.84rem; text-decoration:none; font-weight:600; background:none; border:0; padding:0; cursor:pointer; } .accion { display:inline-flex; align-items:center; gap:.32rem; }
    </style>
</head>
<body>
    @php
        $etiquetas = ['entrada' => 'Entrada registrada', 'salida' => 'Salida registrada', 'salida_refrigerio' => 'Refrigerio iniciado', 'regreso_refrigerio' => 'Regreso de refrigerio registrado'];
        $siguiente = match ($marcacion->tipo) {
            'entrada' => ['Siguiente paso', 'Cuando corresponda, escanea el QR para iniciar tu refrigerio de 1 hora o finalizar tu turno.'],
            'salida_refrigerio' => ['Refrigerio en curso', 'Tu retorno previsto es a las ' . $retornoEsperado->format('H:i:s') . '. Al volver, escanea el QR y registra tu regreso.'],
            'regreso_refrigerio' => ['Jornada en curso', 'Cuando corresponda, escanea el QR para continuar con la siguiente acción de tu turno.'],
            'salida' => ['Jornada completada', 'Tu salida quedó registrada. No tienes ninguna acción pendiente para este turno.'],
        };
    @endphp
    <div class="card">
        <x-heroicon-s-check-circle style="width:2.75rem;height:2.75rem;color:#16a34a;margin:0 auto .75rem;display:block;" />
        <h1>{{ $etiquetas[$marcacion->tipo] }}</h1>
        <div class="hora">{{ $marcacion->fecha_hora->format('H:i:s') }}</div>
        <p class="fecha">{{ $marcacion->fecha_hora->translatedFormat('l d \d\e F') }}</p>
        <div class="siguiente"><strong>{{ $siguiente[0] }}</strong>{{ $siguiente[1] }}</div>
        <div class="acciones">
            <a href="{{ route('marcacion.show') }}" class="accion"><x-heroicon-o-camera style="width:1rem;height:1rem;" /> Ver mi jornada</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="accion"><x-heroicon-o-arrow-left-on-rectangle style="width:1rem;height:1rem;" /> Cerrar sesión</button></form>
        </div>
    </div>
</body>
</html>
