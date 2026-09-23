<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmar marcación</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f3f4f6; color: #111827; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; display: flex; align-items: center; justify-content: center; padding: 1.25rem; }
        .card { background:#fff; border-radius:1rem; box-shadow:0 10px 25px rgba(0,0,0,.06); padding:1.5rem; width:100%; max-width:29rem; }
        h1 { font-size:1.15rem; margin:0; } .sub { color:#6b7280; font-size:.83rem; margin:.2rem 0 1rem; }
        .turno, .ultima, .estado { border-radius:.7rem; padding:.8rem .9rem; font-size:.82rem; line-height:1.45; }
        .turno, .ultima { background:#f9fafb; border:1px solid #e5e7eb; color:#374151; margin-bottom:.7rem; }
        .estado { background:#eff6ff; border:1px solid #bfdbfe; margin: .9rem 0; }
        .estado strong { display:block; color:#1e3a8a; font-size:.98rem; margin-bottom:.16rem; }
        .estado span { color:#475569; }
        .errores { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:.65rem .8rem; border-radius:.6rem; font-size:.8rem; margin-bottom:.85rem; }
        .acciones { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.7rem; margin-top:1rem; }
        .acciones.unica { grid-template-columns:1fr; }
        button.marca { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.22rem; min-height:5.1rem; width:100%; padding:.75rem; border:0; border-radius:.7rem; font-size:.92rem; font-weight:700; color:#fff; cursor:pointer; }
        button.marca small { font-size:.72rem; font-weight:500; opacity:.92; text-align:center; line-height:1.3; }
        .btn-entrada { background:#16a34a; } .btn-salida { background:#dc2626; } .btn-salida_refrigerio { background:#d97706; } .btn-regreso_refrigerio { background:#2563eb; }
        .completo { text-align:center; padding:1rem .5rem .3rem; color:#166534; font-size:.9rem; }
        .links { display:flex; justify-content:center; gap:1rem; margin-top:1.1rem; }
        .links a, .links button { color:#4b5563; font-size:.8rem; text-decoration:none; background:none; border:0; padding:0; cursor:pointer; }
        .link-item { display:inline-flex; align-items:center; gap:.3rem; }
        @media (max-width: 360px) { .acciones { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    @php
        $etiquetas = ['entrada' => 'Marcar ingreso de turno', 'salida' => 'Marcar salida de turno', 'salida_refrigerio' => 'Marcar salida de refrigerio', 'regreso_refrigerio' => 'Marcar ingreso de refrigerio'];
        $iconos = ['entrada' => 'arrow-right-on-rectangle', 'salida' => 'arrow-left-on-rectangle', 'salida_refrigerio' => 'pause-circle', 'regreso_refrigerio' => 'play-circle'];
        $detallesBoton = ['entrada' => 'Inicia tu jornada', 'salida' => 'Cierra tu jornada', 'salida_refrigerio' => 'Refrigerio de 1 hora', 'regreso_refrigerio' => 'Continúa tu jornada'];
        $siguiente = $siguientesTipos[0] ?? null;
        $detalleEstado = match ($siguiente) {
            'entrada' => 'Usa el botón para confirmar tu ingreso de turno.',
            'salida_refrigerio' => 'Usa el botón correspondiente: salida a refrigerio de 1 hora o salida de turno.',
            'regreso_refrigerio' => $retornoEsperado ? 'Tu retorno previsto es a las ' . $retornoEsperado->format('H:i:s') . '. Usa el botón de ingreso de refrigerio.' : 'Usa el botón para registrar tu ingreso de refrigerio.',
            'salida' => 'Usa el botón para registrar tu salida de turno.',
            default => 'No tienes marcaciones pendientes en esta jornada.',
        };
    @endphp
    <div class="card">
        <h1>Confirma tu marcación</h1>
        <p class="sub">{{ $colaborador->nombre_completo }} · {{ $estacion->sucursal->nombre }}{{ $estacion->puntoVenta ? ' · ' . $estacion->puntoVenta->nombre : '' }}</p>
        <div class="turno">Turno: <strong>{{ $asignacion->turno->nombre }}</strong> · {{ \Illuminate\Support\Carbon::parse($asignacion->turno->hora_inicio)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($asignacion->turno->hora_fin)->format('H:i') }}</div>

        @if (isset($errors) && $errors->any())
            <div class="errores">@foreach ($errors->all() as $error){{ $error }}<br>@endforeach</div>
        @endif

        @if ($ultimaMarcacion)
            <div class="ultima">Última marcación: <strong>{{ $etiquetas[$ultimaMarcacion->tipo] }}</strong> · {{ $ultimaMarcacion->fecha_hora->format('H:i:s') }}</div>
        @endif

        <div class="estado"><strong>{{ $siguiente ? 'Siguiente paso: ' . $etiquetas[$siguiente] : 'Jornada completada' }}</strong><span>{{ $detalleEstado }}</span></div>

        @if (empty($siguientesTipos))
            <div class="completo"><x-heroicon-s-check-circle style="width:2rem;height:2rem;display:block;margin:0 auto .4rem;" />Tu turno ya quedó registrado.</div>
        @else
            <div class="acciones {{ count($siguientesTipos) === 1 ? 'unica' : '' }}">
                @foreach ($siguientesTipos as $tipo)
                    <form method="POST" action="{{ route('marcacion.store') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <input type="hidden" name="tipo" value="{{ $tipo }}">
                        <button type="submit" class="marca btn-{{ $tipo }}">
                            <x-dynamic-component :component="'heroicon-o-' . $iconos[$tipo]" style="width:1.35rem;height:1.35rem;" />
                            {{ $etiquetas[$tipo] }}
                            <small>{{ $detallesBoton[$tipo] }}</small>
                        </button>
                    </form>
                @endforeach
            </div>
        @endif

        <div class="links">
            <a href="{{ route('horario.show') }}" class="link-item"><x-heroicon-o-calendar-days style="width:1rem;height:1rem;" /> Mi horario</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="link-item"><x-heroicon-o-arrow-left-on-rectangle style="width:1rem;height:1rem;" /> Cerrar sesión</button></form>
        </div>
    </div>
</body>
</html>
