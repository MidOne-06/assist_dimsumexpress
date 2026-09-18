<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi horario</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #fff; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.06); padding: 1.5rem; width: 100%; max-width: 26rem; }
        h1 { font-size: 1.1rem; margin: 0 0 0.15rem; color: #111827; }
        p.sub { margin: 0 0 1.25rem; color: #6b7280; font-size: 0.85rem; }

        .nav-mes { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .nav-mes a { display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 0.5rem; background: #f3f4f6; color: #4b5563; text-decoration: none; }
        .nav-mes span { font-weight: 600; font-size: 0.95rem; color: #111827; text-transform: capitalize; }

        .lista-dias { display: flex; flex-direction: column; gap: 0.4rem; max-height: 26rem; overflow-y: auto; }
        .fila-dia { display: flex; align-items: center; gap: 0.6rem; padding: 0.5rem 0.6rem; border-radius: 0.6rem; background: #f9fafb; }
        .fila-dia.hoy { background: #eff6ff; border: 1px solid #bfdbfe; }
        .fila-dia-fecha { width: 3.2rem; flex-shrink: 0; text-align: center; }
        .fila-dia-dow { font-size: 0.6rem; text-transform: uppercase; color: #9ca3af; font-weight: 600; }
        .fila-dia-num { font-size: 1rem; font-weight: 700; color: #374151; }
        .fila-dia.hoy .fila-dia-num { color: #2563eb; }
        .fila-dia-turno { flex: 1; min-width: 0; }
        .badge-turno { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .fila-dia-horario { font-size: 0.7rem; color: #9ca3af; margin-top: 0.1rem; }
        .descanso { font-size: 0.8rem; color: #9ca3af; }

        .salir { display: flex; align-items: center; justify-content: center; gap: 0.35rem; text-align: center; margin-top: 1.25rem; color: #6b7280; font-size: 0.8rem; text-decoration: none; }
        .volver { display: flex; align-items: center; justify-content: center; gap: 0.35rem; text-align: center; margin-top: 0.5rem; color: #2563eb; font-size: 0.8rem; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Mi horario</h1>
        <p class="sub">{{ $colaborador->nombre_completo }} · {{ $colaborador->sucursal->nombre }}{{ $colaborador->puntoVenta ? ' · ' . $colaborador->puntoVenta->nombre : '' }}</p>

        <div class="nav-mes">
            <a href="{{ route('horario.show', ['mes' => $mesAnterior]) }}">
                <x-heroicon-o-chevron-left style="width: 1rem; height: 1rem;" />
            </a>
            <span>{{ $mesLabel }}</span>
            <a href="{{ route('horario.show', ['mes' => $mesSiguiente]) }}">
                <x-heroicon-o-chevron-right style="width: 1rem; height: 1rem;" />
            </a>
        </div>

        <div class="lista-dias">
            @foreach ($dias as $dia)
                @php $asignacion = $dia['asignacion']; @endphp
                <div class="fila-dia {{ $dia['hoy'] ? 'hoy' : '' }}">
                    <div class="fila-dia-fecha">
                        <div class="fila-dia-dow">{{ $dia['fecha']->locale('es')->translatedFormat('D') }}</div>
                        <div class="fila-dia-num">{{ $dia['fecha']->day }}</div>
                    </div>
                    <div class="fila-dia-turno">
                        @if ($asignacion && $asignacion->turno)
                            @php $color = \App\Filament\Pages\CalendarioTurnos::colorParaTurno($asignacion->turno->id); @endphp
                            <span class="badge-turno" style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }};">
                                {{ $asignacion->turno->nombre }}
                            </span>
                            <div class="fila-dia-horario">
                                {{ \Illuminate\Support\Carbon::parse($asignacion->turno->hora_inicio)->format('H:i') }}–{{ \Illuminate\Support\Carbon::parse($asignacion->turno->hora_fin)->format('H:i') }}
                            </div>
                        @else
                            <span class="descanso">Descanso</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <a href="{{ route('marcacion.show') }}" class="volver">
            <x-heroicon-o-arrow-left style="width: 1rem; height: 1rem;" />
            Volver a marcar asistencia
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="all: unset; cursor: pointer;" class="salir">
                <x-heroicon-o-arrow-left-on-rectangle style="width: 1rem; height: 1rem;" />
                Cerrar sesión
            </button>
        </form>
    </div>
</body>
</html>
