<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi jornada</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f3f4f6; color: #111827; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; display: flex; align-items: center; justify-content: center; padding: 1.25rem; }
        .card { background: #fff; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,.06); padding: 1.5rem; width: 100%; max-width: 29rem; }
        .encabezado { display: flex; gap: .8rem; align-items: flex-start; margin-bottom: 1.15rem; }
        h1 { font-size: 1.2rem; line-height: 1.3; margin: 0; }
        .sub { color: #6b7280; font-size: .83rem; line-height: 1.45; margin: .18rem 0 0; }
        .icono { flex: 0 0 auto; width: 2.35rem; height: 2.35rem; color: #2563eb; }
        .turno, .estado, .alerta { border-radius: .7rem; padding: .85rem .95rem; margin-bottom: .85rem; }
        .turno { background: #f9fafb; border: 1px solid #e5e7eb; font-size: .83rem; color: #374151; }
        .turno strong { color: #111827; }
        .estado { border: 1px solid #bfdbfe; background: #eff6ff; }
        .estado-titulo { display: flex; align-items: center; gap: .4rem; color: #1d4ed8; font-size: .75rem; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; }
        .estado-accion { margin: .32rem 0 .18rem; font-size: 1rem; font-weight: 700; color: #1e3a8a; }
        .estado-detalle { margin: 0; color: #475569; font-size: .82rem; line-height: 1.45; }
        .alerta { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: .84rem; line-height: 1.45; }
        .ultimo { color: #6b7280; font-size: .78rem; margin: -.15rem 0 .9rem; }
        .guia { color: #4b5563; font-size: .82rem; line-height: 1.45; margin: 1rem 0 .75rem; text-align: center; }
        .links { display: flex; justify-content: center; gap: 1rem; margin-top: 1.1rem; }
        .links a, .links button { color: #4b5563; font-size: .8rem; text-decoration: none; background: none; border: 0; padding: 0; cursor: pointer; }
        .link-item { display: inline-flex; align-items: center; gap: .3rem; }
    </style>
</head>
<body>
    @php
        $etiquetas = [
            'entrada' => 'Registrar entrada',
            'salida' => 'Finalizar turno',
            'salida_refrigerio' => 'Iniciar refrigerio',
            'regreso_refrigerio' => 'Registrar regreso de refrigerio',
        ];
        $siguiente = $siguientesTipos[0] ?? null;
        $detalleSiguiente = match ($siguiente) {
            'entrada' => 'Escanea el QR del punto de venta y confirma tu ingreso.',
            'salida_refrigerio' => 'Escanea el QR para elegir entre iniciar tu refrigerio de 1 hora o finalizar tu turno.',
            'regreso_refrigerio' => $retornoEsperado
                ? 'Tu retorno previsto es a las ' . $retornoEsperado->format('H:i:s') . '. Escanea el QR para registrarlo.'
                : 'Escanea el QR del punto de venta para registrar tu regreso.',
            'salida' => 'Escanea el QR del punto de venta para registrar tu salida.',
            default => null,
        };
    @endphp
    <div class="card">
        <div class="encabezado">
            <x-heroicon-o-clock class="icono" />
            <div>
                <h1>Hola, {{ $colaborador->nombre_completo }}</h1>
                <p class="sub">{{ $colaborador->sucursal->nombre }}{{ $colaborador->puntoVenta ? ' · ' . $colaborador->puntoVenta->nombre : '' }}</p>
            </div>
        </div>

        @if ($asignacion)
            <div class="turno">
                Turno de hoy: <strong>{{ $asignacion->turno->nombre }}</strong> · {{ \Illuminate\Support\Carbon::parse($asignacion->turno->hora_inicio)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($asignacion->turno->hora_fin)->format('H:i') }}
            </div>

            @if ($siguiente)
                <div class="estado">
                    <div class="estado-titulo"><x-heroicon-o-arrow-right-circle style="width:1rem;height:1rem;" /> Tu siguiente paso</div>
                    <div class="estado-accion">{{ $etiquetas[$siguiente] }}</div>
                    <p class="estado-detalle">{{ $detalleSiguiente }}</p>
                </div>
                @if ($ultimaMarcacion)
                    <p class="ultimo">Última marcación: {{ $etiquetas[$ultimaMarcacion->tipo] }} · {{ $ultimaMarcacion->fecha_hora->format('H:i:s') }}</p>
                @endif
                <p class="guia">Usa la cámara para escanear el QR dinámico mostrado en tu punto de venta.</p>
                @include('marcacion.partials.escaner')
            @else
                <div class="estado" style="border-color:#bbf7d0;background:#f0fdf4;">
                    <div class="estado-titulo" style="color:#15803d;"><x-heroicon-o-check-circle style="width:1rem;height:1rem;" /> Jornada completada</div>
                    <p class="estado-detalle" style="margin-top:.35rem;">No tienes marcaciones pendientes para este turno.</p>
                </div>
            @endif
        @else
            <div class="alerta">No tienes un turno habilitado para marcar en este momento. Revisa tu horario o consulta con tu supervisora.</div>
        @endif

        <div class="links">
            <a href="{{ route('horario.show') }}" class="link-item"><x-heroicon-o-calendar-days style="width:1rem;height:1rem;" /> Mi horario</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="link-item"><x-heroicon-o-arrow-left-on-rectangle style="width:1rem;height:1rem;" /> Cerrar sesión</button>
            </form>
        </div>
    </div>
</body>
</html>
