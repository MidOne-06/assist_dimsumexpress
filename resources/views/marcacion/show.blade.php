<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marcar asistencia</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: #fff; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.06); padding: 1.75rem; width: 100%; max-width: 24rem; }
        h1 { font-size: 1.1rem; margin: 0 0 0.15rem; color: #111827; }
        p.sub { margin: 0 0 1.25rem; color: #6b7280; font-size: 0.85rem; }
        .errores { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.6rem 0.8rem; border-radius: 0.5rem; font-size: 0.8rem; margin-bottom: 1rem; }
        .ultima { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem 0.9rem; font-size: 0.8rem; color: #374151; margin-bottom: 1.25rem; }
        button.marca { display: block; width: 100%; padding: 0.85rem; border: none; border-radius: 0.6rem; font-size: 1rem; font-weight: 600; color: #fff; cursor: pointer; margin-bottom: 0.75rem; }
        .btn-entrada { background: #16a34a; }
        .btn-salida { background: #dc2626; }
        .btn-salida_refrigerio { background: #d97706; }
        .btn-regreso_refrigerio { background: #2563eb; }
        .completo { text-align: center; color: #4b5563; font-size: 0.9rem; padding: 1rem 0; }
        .salir { display: block; text-align: center; margin-top: 1rem; color: #6b7280; font-size: 0.8rem; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Hola, {{ $colaborador->nombre_completo }}</h1>
        <p class="sub">{{ $colaborador->sucursal->nombre }}{{ $colaborador->puntoVenta ? ' · ' . $colaborador->puntoVenta->nombre : '' }}</p>

        @if ($errors->any())
            <div class="errores">
                @foreach ($errors->all() as $error)
                    {{ $error }}<br>
                @endforeach
            </div>
        @endif

        @if ($ultimaMarcacion)
            @php
                $etiquetas = [
                    'entrada' => 'Entrada',
                    'salida' => 'Salida',
                    'salida_refrigerio' => 'Salida a refrigerio',
                    'regreso_refrigerio' => 'Regreso de refrigerio',
                ];
            @endphp
            <div class="ultima">
                Última marcación hoy: <strong>{{ $etiquetas[$ultimaMarcacion->tipo] }}</strong>
                a las {{ $ultimaMarcacion->fecha_hora->format('H:i') }}
            </div>
        @endif

        @if (empty($siguientesTipos))
            <div class="completo">Ya completaste tu jornada de hoy. ¡Buen trabajo!</div>
        @else
            @foreach ($siguientesTipos as $tipo)
                @php
                    $etiquetas = [
                        'entrada' => 'Registrar entrada',
                        'salida' => 'Registrar salida',
                        'salida_refrigerio' => 'Salida a refrigerio',
                        'regreso_refrigerio' => 'Regreso de refrigerio',
                    ];
                @endphp
                <form method="POST" action="{{ route('marcacion.store') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="tipo" value="{{ $tipo }}">
                    <button type="submit" class="marca btn-{{ $tipo }}">{{ $etiquetas[$tipo] }}</button>
                </form>
            @endforeach
        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="all: unset; cursor: pointer;" class="salir">Cerrar sesión</button>
        </form>
    </div>
</body>
</html>
