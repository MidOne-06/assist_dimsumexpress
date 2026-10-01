<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi horario — {{ $apariencia->nombre() }}</title>
    <link rel="icon" href="{{ $apariencia->iconoUrl() }}">
    <script>
        try {
            const tema = localStorage.getItem('theme');
            if (tema === 'dark' || tema === 'light') document.documentElement.classList.add(tema);
        } catch (_) {}
    </script>
    <style>
        :root { color-scheme: light; --primary: {{ $apariencia->colorPrimario() }}; --page:#f8fafc; --surface:#fff; --surface-soft:#f8fafc; --ink:#111827; --muted:#667085; --line:#e4e7ec; --shadow:rgb(15 23 42 / .10); }
        @media (prefers-color-scheme: dark) { :root { color-scheme:dark; --page:#0f172a; --surface:#18181b; --surface-soft:#27272a; --ink:#f8fafc; --muted:#a1a1aa; --line:#3f3f46; --shadow:rgb(2 6 23 / .32); } }
        html.dark { color-scheme:dark; --page:#0f172a; --surface:#18181b; --surface-soft:#27272a; --ink:#f8fafc; --muted:#a1a1aa; --line:#3f3f46; --shadow:rgb(2 6 23 / .32); }
        html.light { color-scheme:light; --page:#f8fafc; --surface:#fff; --surface-soft:#f8fafc; --ink:#111827; --muted:#667085; --line:#e4e7ec; --shadow:rgb(15 23 42 / .10); }
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; padding:clamp(1rem,3vw,2.5rem); background:var(--page); color:var(--ink); font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
        .shell { width:min(100%,72rem); margin:auto; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.125rem; }
        .brand { display:flex; min-width:0; align-items:center; gap:.75rem; }
        .brand-logo { width:auto; height:2.5rem; max-width:9rem; object-fit:contain; }
        .brand-name { overflow:hidden; color:var(--ink); font-size:.9375rem; font-weight:700; text-overflow:ellipsis; white-space:nowrap; }
        .logout { display:inline-flex; align-items:center; gap:.45rem; min-height:2.5rem; padding:0 .875rem; border:1px solid var(--line); border-radius:.625rem; background:var(--surface); color:var(--ink); font:inherit; font-size:.875rem; font-weight:600; cursor:pointer; }
        .logout:hover { border-color:color-mix(in srgb,var(--primary) 40%,var(--line)); color:var(--primary); }
        .page-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
        h1 { margin:0; font-size:clamp(1.375rem,3vw,1.625rem); letter-spacing:-.03em; line-height:1.2; }
        .identity { margin:.4rem 0 0; color:var(--muted); font-size:.875rem; line-height:1.45; }
        .today-link { display:inline-flex; flex:0 0 auto; align-items:center; gap:.4rem; min-height:2.5rem; padding:0 .875rem; border:1px solid var(--line); border-radius:.625rem; background:var(--surface); color:var(--ink); font-size:.875rem; font-weight:600; text-decoration:none; }
        .today-link:hover { border-color:var(--primary); color:var(--primary); }
        .panel { overflow:hidden; border:1px solid var(--line); border-radius:.75rem; background:var(--surface); box-shadow:0 .5rem 1.25rem var(--shadow); }
        .panel-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.875rem 1rem; border-bottom:1px solid var(--line); }
        .month-nav { display:flex; align-items:center; gap:.625rem; }
        .month { min-width:10.5rem; text-align:center; color:var(--ink); font-size:.9375rem; font-weight:700; text-transform:capitalize; }
        .nav-button { display:inline-grid; width:2.25rem; height:2.25rem; place-items:center; border:1px solid var(--line); border-radius:.5rem; background:var(--surface-soft); color:var(--muted); text-decoration:none; }
        .nav-button:hover { border-color:var(--primary); color:var(--primary); }
        .legend { color:var(--muted); font-size:.8125rem; }
        .days { display:grid; grid-template-columns:repeat(auto-fit,minmax(14rem,1fr)); gap:.625rem; padding:.875rem; }
        .day { position:relative; display:grid; min-height:5rem; grid-template-columns:3rem minmax(0,1fr); gap:.625rem; align-items:center; padding:.75rem; border:1px solid var(--line); border-radius:.625rem; background:var(--surface-soft); }
        .day.today { border-color:color-mix(in srgb,var(--primary) 55%,var(--line)); background:color-mix(in srgb,var(--primary) 8%,var(--surface)); box-shadow:0 0 0 1px color-mix(in srgb,var(--primary) 12%,transparent); }
        .date { display:grid; place-items:center; align-content:center; min-height:2.875rem; border-radius:.5rem; background:var(--surface); border:1px solid var(--line); }
        .dow { color:var(--muted); font-size:.625rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
        .number { margin-top:.1rem; color:var(--ink); font-size:1.125rem; font-weight:800; line-height:1; }
        .today .number { color:var(--primary); }
        .content { min-width:0; }
        .badge { display:inline-flex; max-width:100%; align-items:center; gap:.35rem; padding:.25rem .55rem; border-radius:999px; font-size:.75rem; font-weight:700; line-height:1.25; }
        .hours { margin-top:.375rem; color:var(--muted); font-size:.8125rem; font-variant-numeric:tabular-nums; }
        .rest { color:var(--muted); font-size:.875rem; }
        .today-badge { position:absolute; top:.55rem; right:.55rem; padding:.15rem .4rem; border-radius:999px; background:color-mix(in srgb,var(--primary) 16%,transparent); color:var(--primary); font-size:.625rem; font-weight:800; text-transform:uppercase; }
        .actions { display:flex; justify-content:flex-start; margin-top:1.25rem; }
        .back { display:inline-flex; align-items:center; gap:.45rem; min-height:2.5rem; padding:0 .875rem; border:1px solid color-mix(in srgb,var(--primary) 35%,var(--line)); border-radius:.625rem; background:color-mix(in srgb,var(--primary) 9%,var(--surface)); color:var(--primary); font-size:.875rem; font-weight:700; text-decoration:none; }
        .back:hover { background:color-mix(in srgb,var(--primary) 16%,var(--surface)); }
        @media (max-width:640px) { body { padding:.75rem; } .topbar { margin-bottom:.875rem; } .brand-name { display:none; } .brand-logo { height:2rem; } .page-heading { align-items:flex-end; margin-bottom:.875rem; } h1 { font-size:1.375rem; } .identity { font-size:.8125rem; } .today-link span { display:none; } .panel { border-radius:.625rem; box-shadow:none; } .panel-head { padding:.75rem .875rem; } .legend { display:none; } .month-nav { width:100%; justify-content:space-between; } .month { min-width:0; font-size:.875rem; } .days { grid-template-columns:1fr; padding:0; gap:0; } .day { min-height:3.875rem; grid-template-columns:2.75rem minmax(0,1fr); gap:.625rem; padding:.625rem .875rem; border:0; border-radius:0; border-bottom:1px solid var(--line); background:transparent; } .day:last-child { border-bottom:0; } .day.today { box-shadow:none; } .date { min-height:2.5rem; border:0; border-radius:0; background:transparent; } .number { font-size:1rem; } .today-badge { top:.4rem; right:.75rem; } .logout span { display:none; } .logout { width:2.5rem; justify-content:center; padding:0; } .actions { margin-top:1rem; } }
    </style>
</head>
<body>
    @php
        $hoyEnMes = collect($dias)->contains(fn (array $dia) => $dia['hoy']);
    @endphp
    <main class="shell">
        <header class="topbar">
            <div class="brand">
                <img class="brand-logo" src="{{ $apariencia->logoUrl() }}" alt="{{ $apariencia->nombre() }}">
                <span class="brand-name">{{ $apariencia->nombre() }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout" aria-label="Cerrar sesión">
                    <x-heroicon-o-arrow-left-on-rectangle style="width:1rem;height:1rem;" />
                    <span>Cerrar sesión</span>
                </button>
            </form>
        </header>

        <section class="page-heading">
            <div>
                <h1>Mi horario</h1>
                <p class="identity">{{ $colaborador->nombre_completo }} · {{ $colaborador->sucursal->nombre }}{{ $colaborador->puntoVenta ? ' · ' . $colaborador->puntoVenta->nombre : '' }}</p>
            </div>
            @if (! $hoyEnMes)
                <a class="today-link" href="{{ route('horario.show') }}"><x-heroicon-o-calendar-days style="width:1rem;height:1rem;" /><span>Mes actual</span></a>
            @endif
        </section>

        <section class="panel" aria-label="Horario de {{ $mesLabel }}">
            <div class="panel-head">
                <nav class="month-nav" aria-label="Cambiar mes">
                    <a class="nav-button" href="{{ route('horario.show', ['mes' => $mesAnterior]) }}" aria-label="Mes anterior"><x-heroicon-o-chevron-left style="width:1.125rem;height:1.125rem;" /></a>
                    <span class="month">{{ $mesLabel }}</span>
                    <a class="nav-button" href="{{ route('horario.show', ['mes' => $mesSiguiente]) }}" aria-label="Mes siguiente"><x-heroicon-o-chevron-right style="width:1.125rem;height:1.125rem;" /></a>
                </nav>
                <span class="legend">Turnos asignados</span>
            </div>

            <div class="days">
                @foreach ($dias as $dia)
                    @php $asignacion = $dia['asignacion']; @endphp
                    <article class="day {{ $dia['hoy'] ? 'today' : '' }}" @if($dia['hoy']) id="hoy" @endif>
                        @if ($dia['hoy'])<span class="today-badge">Hoy</span>@endif
                        <div class="date">
                            <span class="dow">{{ $dia['fecha']->locale('es')->translatedFormat('D') }}</span>
                            <span class="number">{{ $dia['fecha']->day }}</span>
                        </div>
                        <div class="content">
                            @if ($asignacion && $asignacion->turno)
                                @php $color = \App\Filament\Pages\CalendarioTurnos::colorParaTurno($asignacion->turno->id); @endphp
                                <span class="badge" style="background-color:{{ $color['bg'] }};color:{{ $color['text'] }};">{{ $asignacion->turno->nombre }}</span>
                                <div class="hours">{{ \Illuminate\Support\Carbon::parse($asignacion->turno->hora_inicio)->format('H:i') }} – {{ \Illuminate\Support\Carbon::parse($asignacion->turno->hora_fin)->format('H:i') }}</div>
                            @else
                                <span class="rest">Descanso</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="actions">
            <a href="{{ route('marcacion.show') }}" class="back"><x-heroicon-o-arrow-left style="width:1rem;height:1rem;" /> Volver a marcar asistencia</a>
        </div>
    </main>
</body>
</html>
