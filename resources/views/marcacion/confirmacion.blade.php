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
            'entrada' => ['Siguiente paso', 'Cuando corresponda, escanea el QR para iniciar tu refrigerio de 1 hora o finalizar tu turno.'],
            'salida_refrigerio' => ['Refrigerio en curso', $retornoEsperado ? 'Tu retorno previsto es a las ' . $retornoEsperado->format('H:i:s') . '. Al volver, escanea el QR y registra tu regreso.' : 'Al volver, escanea el QR y registra tu ingreso de refrigerio.'],
            'regreso_refrigerio' => ['Jornada en curso', 'Cuando corresponda, escanea el QR para continuar con la siguiente acción de tu turno.'],
            'salida' => ['Jornada completada', 'Tu salida quedó registrada. No tienes ninguna acción pendiente para este turno.'],
        };
    @endphp
    <div class="card">
        <x-heroicon-s-check-circle style="width:2.75rem;height:2.75rem;color:#16a34a;margin:0 auto .75rem;display:block;" />
        <h1>{{ $etiquetas[$marcacion->tipo] }}</h1>
        <div class="hora">{{ $marcacion->fecha_hora->format('H:i:s') }}</div>
        <p class="fecha">{{ $marcacion->fecha_hora->translatedFormat('l d \d\e F') }}</p>
        @if ($sinTurnoAsignado)
            <div class="siguiente" style="background:var(--app-warning-bg);border-color:color-mix(in srgb,var(--app-warning) 38%,var(--app-border));">
                <strong style="color:var(--app-warning);">Marcación excepcional</strong>
                No había un turno asignado. El registro fue guardado con la estación y hora real para revisión administrativa.
            </div>
        @endif
        <div class="siguiente"><strong>{{ $siguiente[0] }}</strong>{{ $siguiente[1] }}</div>
        @if ($resumenJornada)
            <div class="siguiente" style="background:var(--app-success-bg);border-color:color-mix(in srgb,var(--app-success) 38%,var(--app-border));">
                <strong style="color:var(--app-success);">Horas efectivas trabajadas</strong>
                {{ intdiv($resumenJornada['efectivos_segundos'], 3600) }} h {{ intdiv($resumenJornada['efectivos_segundos'] % 3600, 60) }} min {{ $resumenJornada['efectivos_segundos'] % 60 }} s · Meta {{ intdiv($resumenJornada['objetivo_segundos'], 3600) }} h
                @if ($resumenJornada['extras_segundos']) · Extras {{ intdiv($resumenJornada['extras_segundos'], 3600) }} h {{ intdiv($resumenJornada['extras_segundos'] % 3600, 60) }} min {{ $resumenJornada['extras_segundos'] % 60 }} s @endif
            </div>
        @endif
        <div class="acciones">
            <a href="{{ route('marcacion.show') }}" class="accion"><x-heroicon-o-camera style="width:1rem;height:1rem;" /> Ver mi jornada</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="accion"><x-heroicon-o-arrow-left-on-rectangle style="width:1rem;height:1rem;" /> Cerrar sesión</button></form>
        </div>
    </div>
</body>
</html>
