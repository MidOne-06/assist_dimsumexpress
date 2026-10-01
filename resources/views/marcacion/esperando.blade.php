<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marcar asistencia — {{ $apariencia->nombre() }}</title>
    <link rel="icon" href="{{ $apariencia->iconoUrl() }}">
    <script>
        try {
            const tema = localStorage.getItem('theme');
            if (tema === 'dark' || tema === 'light') document.documentElement.classList.add(tema);
        } catch (_) {}
    </script>
    <style>
        :root { color-scheme: light; --primary: {{ $apariencia->colorPrimario() }}; --page:#f8fafc; --surface:#fff; --soft:#f8fafc; --ink:#111827; --muted:#667085; --line:#e4e7ec; }
        @media (prefers-color-scheme: dark) { :root { color-scheme:dark; --page:#0f172a; --surface:#18181b; --soft:#27272a; --ink:#f8fafc; --muted:#a1a1aa; --line:#3f3f46; } }
        html.dark { color-scheme:dark; --page:#0f172a; --surface:#18181b; --soft:#27272a; --ink:#f8fafc; --muted:#a1a1aa; --line:#3f3f46; }
        html.light { color-scheme:light; --page:#f8fafc; --surface:#fff; --soft:#f8fafc; --ink:#111827; --muted:#667085; --line:#e4e7ec; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100dvh; background:var(--page); color:var(--ink); font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; display:flex; align-items:center; justify-content:center; padding:clamp(1rem,4vw,2rem); }
        .card { background:var(--surface); border:1px solid var(--line); border-radius:.875rem; box-shadow:0 .75rem 1.75rem rgb(15 23 42 / .10); padding:1.125rem; width:100%; max-width:30rem; }
        .brand { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding-bottom:1rem; margin-bottom:1rem; border-bottom:1px solid var(--line); }
        .brand-logo { width:auto; height:2.25rem; max-width:8rem; object-fit:contain; }
        .brand-label { color:var(--muted); font-size:.75rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
        .encabezado { display: flex; gap: .8rem; align-items: flex-start; margin-bottom: 1.15rem; }
        h1 { font-size:1.125rem; line-height:1.3; margin:0; letter-spacing:-.015em; }
        .sub { color:var(--muted); font-size:.875rem; line-height:1.45; margin:.18rem 0 0; }
        .icono { flex:0 0 auto; width:2.35rem; height:2.35rem; color:var(--primary); }
        .turno, .estado, .alerta { border-radius:.625rem; padding:.75rem .875rem; margin-bottom:.75rem; }
        .turno { background:var(--soft); border:1px solid var(--line); font-size:.875rem; color:var(--muted); }
        .turno strong { color:var(--ink); }
        .estado { border:1px solid color-mix(in srgb,var(--primary) 33%,var(--line)); background:color-mix(in srgb,var(--primary) 8%,var(--surface)); }
        .estado-titulo { display:flex; align-items:center; gap:.4rem; color:var(--primary); font-size:.75rem; font-weight:700; letter-spacing:.02em; text-transform:uppercase; }
        .estado-accion { margin:.32rem 0 .18rem; font-size:1rem; font-weight:700; color:var(--ink); }
        .estado-detalle { margin:0; color:var(--muted); font-size:.875rem; line-height:1.45; }
        .alerta { background:color-mix(in srgb,#f59e0b 10%,var(--surface)); border:1px solid color-mix(in srgb,#f59e0b 45%,var(--line)); color:color-mix(in srgb,#b45309 76%,var(--ink)); font-size:.875rem; line-height:1.45; }
        .ultimo { color:var(--muted); font-size:.8125rem; margin:-.15rem 0 .9rem; }
        .guia { color:var(--muted); font-size:.8125rem; line-height:1.45; margin:1rem 0 .75rem; text-align:center; }
        .links { display:flex; justify-content:center; gap:1rem; margin-top:1.1rem; }
        .links a, .links button { color:var(--muted); font-size:.8125rem; text-decoration:none; background:none; border:0; padding:0; cursor:pointer; }
        .link-item { display: inline-flex; align-items: center; gap: .3rem; }
        @media (max-width:640px) { body { display:block; padding:.75rem; } .card { max-width:none; margin:auto; padding:1rem; } .brand { padding-bottom:.75rem; margin-bottom:.875rem; } .brand-logo { height:2rem; } .encabezado { margin-bottom:.875rem; } .icono { width:2rem; height:2rem; } button.mp-escanear { min-height:3.25rem; border-radius:.625rem; font-size:.9375rem; } .links { justify-content:space-between; margin-top:.875rem; } }
    </style>
</head>
<body>
    @php
        $etiquetas = [
            'entrada' => 'Marcar ingreso de turno',
            'salida' => 'Marcar salida de turno',
            'salida_refrigerio' => 'Marcar salida de refrigerio',
            'regreso_refrigerio' => 'Marcar ingreso de refrigerio',
        ];
        $siguiente = $siguientesTipos[0] ?? null;
        $detalleSiguiente = match ($siguiente) {
            'entrada' => 'Después de escanear el QR, podrás confirmar: Marcar ingreso de turno.',
            'salida_refrigerio' => 'Después de escanear el QR, podrás elegir: Marcar salida de refrigerio o Marcar salida de turno.',
            'regreso_refrigerio' => $retornoEsperado
                ? 'Tu retorno previsto es a las ' . $retornoEsperado->format('H:i:s') . '. Después de escanear el QR, podrás confirmar tu ingreso de refrigerio.'
                : 'Después de escanear el QR, podrás confirmar tu ingreso de refrigerio.',
            'salida' => 'Después de escanear el QR, podrás confirmar: Marcar salida de turno.',
            default => null,
        };
    @endphp
    <div class="card">
        <div class="brand"><img class="brand-logo" src="{{ $apariencia->logoUrl() }}" alt="{{ $apariencia->nombre() }}"><span class="brand-label">Marcación</span></div>
        <div class="encabezado">
            <x-heroicon-o-clock class="icono" />
            <div>
                <h1>Hola, {{ $colaborador->nombre_completo }}</h1>
                <p class="sub">{{ $colaborador->sucursal->nombre }}{{ $colaborador->puntoVenta ? ' · ' . $colaborador->puntoVenta->nombre : '' }}</p>
            </div>
        </div>

        @if ($asignacion)
            <div class="turno">
                Turno de hoy: <strong>{{ $asignacion->turno->nombre }}</strong> · {{ $asignacion->turno->rangoHorario() }}
                · {{ intdiv($asignacion->turno->horas_efectivas_objetivo_minutos, 60) }} h efectivas{{ $asignacion->turno->incluye_refrigerio ? ' + ' . $asignacion->turno->refrigerio_minutos . ' min de refrigerio' : '' }}
            </div>

            @if (($resumenJornada['efectivos_segundos'] ?? null) !== null)
                <div class="estado" style="border-color:#bbf7d0;background:#f0fdf4;">
                    <div class="estado-titulo" style="color:#15803d;">Horas efectivas</div>
                    <p class="estado-detalle" style="margin-top:.35rem;">{{ intdiv($resumenJornada['efectivos_segundos'], 3600) }} h {{ intdiv($resumenJornada['efectivos_segundos'] % 3600, 60) }} min {{ $resumenJornada['efectivos_segundos'] % 60 }} s · Meta: {{ intdiv($resumenJornada['objetivo_segundos'], 3600) }} h{{ $resumenJornada['extras_segundos'] ? ' · Extras: ' . intdiv($resumenJornada['extras_segundos'], 3600) . ' h ' . intdiv($resumenJornada['extras_segundos'] % 3600, 60) . ' min ' . ($resumenJornada['extras_segundos'] % 60) . ' s' : '' }}</p>
                </div>
            @endif

            @if ($siguiente)
                <div class="estado">
                    <div class="estado-titulo"><x-heroicon-o-arrow-right-circle style="width:1rem;height:1rem;" /> Tu siguiente paso</div>
                    <div class="estado-accion">Escanea el QR para continuar</div>
                    <p class="estado-detalle">{{ $detalleSiguiente }}</p>
                </div>
                @if ($ultimaMarcacion)
                    <p class="ultimo">Última marcación: {{ $etiquetas[$ultimaMarcacion->tipo] }} · {{ $ultimaMarcacion->fecha_hora->format('H:i:s') }}</p>
                @endif
                <p class="guia">Cada marcación requiere escanear un QR dinámico nuevo mostrado en tu punto de venta.</p>
                @include('marcacion.partials.escaner')
                <style>
                    button.mp-escanear { min-height:3.25rem; border-radius:.75rem; background:var(--primary) !important; box-shadow:0 .5rem 1rem color-mix(in srgb,var(--primary) 22%,transparent); }
                    button.mp-escanear:hover { filter:brightness(.94); }
                </style>
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
