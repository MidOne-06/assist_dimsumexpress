<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marcación — {{ $apariencia->nombre() }}</title><link rel="icon" href="{{ $apariencia->iconoUrl() }}"><x-pwa-head />
    <style>
        :root{color-scheme:light;--primary:{{ $apariencia->colorPrimario() }};--page:#f8fafc;--surface:#fff;--soft:#f8fafc;--ink:#172033;--muted:#667085;--line:#e4e7ec;--success:#15803d;--warning:#b54708;--info:#175cd3;--danger:#b42318}
        @media(prefers-color-scheme:dark){:root{color-scheme:dark;--page:#101828;--surface:#1d2939;--soft:#182230;--ink:#f9fafb;--muted:#cbd5e1;--line:#344054;--success:#86efac;--warning:#facc15;--info:#93c5fd;--danger:#fca5a5}}
        *{box-sizing:border-box}body{min-height:100dvh;margin:0;background:var(--page);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;padding:1rem}.shell{width:min(100%,31rem);margin:clamp(1rem,8vh,4rem) auto;padding:1.125rem;background:var(--surface);border:1px solid var(--line);border-radius:.875rem}.brand{display:flex;justify-content:space-between;align-items:center;padding-bottom:.875rem;border-bottom:1px solid var(--line)}.brand img{height:2.1rem;max-width:8rem;object-fit:contain}.brand span{font-size:.75rem;color:var(--muted);font-weight:700;letter-spacing:.05em;text-transform:uppercase}.identity{padding:.875rem 0 .75rem}.identity h1{margin:0;font-size:1.125rem;line-height:1.3}.identity p{margin:.2rem 0 0;color:var(--muted);font-size:.875rem}.estado{padding:.75rem .875rem;margin-bottom:1rem;border:1px solid color-mix(in srgb,var(--primary) 34%,var(--line));border-radius:.625rem;background:color-mix(in srgb,var(--primary) 7%,var(--surface));font-size:.8125rem;line-height:1.45}.estado strong{display:block;color:var(--primary);font-size:.875rem}.estado span{color:var(--muted)}.section-label{margin:0 0 .625rem;font-size:.8125rem;font-weight:700;color:var(--muted)}.acciones{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.625rem}.accion{display:flex;min-height:6.75rem;flex-direction:column;align-items:flex-start;justify-content:center;gap:.3rem;padding:.75rem;border:1px solid var(--line);border-radius:.625rem;background:var(--surface);color:var(--ink);font:inherit;text-align:left;cursor:pointer}.accion svg{width:1.25rem;height:1.25rem}.accion strong{font-size:.875rem;line-height:1.25}.accion small{font-size:.75rem;line-height:1.3}.accion:disabled{cursor:not-allowed;background:var(--soft);opacity:.62}.accion:not(:disabled):focus-visible{outline:3px solid color-mix(in srgb,var(--primary) 30%,transparent);outline-offset:2px}.ingreso{color:var(--success)}.refrigerio-salida{color:var(--warning)}.refrigerio-regreso{color:var(--info)}.salida{color:var(--danger)}.accion small{opacity:.8}.links{display:flex;justify-content:space-between;margin-top:1rem}.links a,.links button{display:inline-flex;align-items:center;gap:.35rem;min-height:2.75rem;border:0;background:none;padding:0;color:var(--muted);font:inherit;font-size:.8125rem;text-decoration:none;cursor:pointer}.links svg{width:1rem;height:1rem}@media(max-width:380px){body{padding:.5rem}.shell{margin:.5rem auto;padding:1rem}.acciones{grid-template-columns:1fr}.accion{min-height:4.75rem}}
    </style>
</head>
<body>
@php
    $acciones=$acciones??[];
    $meta=['entrada'=>['Marcar ingreso','Inicia tu jornada','arrow-right-on-rectangle','ingreso'],'salida_refrigerio'=>['Salida a refrigerio','Inicia tu descanso','pause-circle','refrigerio-salida'],'regreso_refrigerio'=>['Regreso de refrigerio','Continúa tu jornada','play-circle','refrigerio-regreso'],'salida'=>['Marcar salida','Finaliza tu jornada','arrow-left-on-rectangle','salida']];
    $siguiente=$siguientesTipos[0]??null;
@endphp
<main class="shell">
    <header class="brand"><img src="{{ $apariencia->logoUrl() }}" alt="{{ $apariencia->nombre() }}"><span>Marcación</span></header>
    <section class="identity"><h1>{{ $colaborador->nombre_completo }}</h1><p>{{ $colaborador->sucursal->nombre }}{{ $colaborador->puntoVenta ? ' · '.$colaborador->puntoVenta->nombre : '' }}</p></section>
    <div class="estado"><strong>{{ $siguiente && isset($meta[$siguiente]) ? 'Siguiente paso: '.$meta[$siguiente][0] : 'Jornada completada' }}</strong><span>{{ $siguiente ? 'Selecciona la acción y luego escanea el QR actual de tu estación.' : 'No tienes acciones pendientes para hoy.' }}</span></div>
    <p class="section-label">Selecciona la acción que deseas registrar</p>
    <div class="acciones" aria-label="Acciones de marcación">
        @foreach($acciones as $accion)
            @php([$titulo,$detalle,$icono,$clase]=$meta[$accion['tipo']])
            <button type="button" class="accion {{ $clase }}" data-mp-accion="{{ $accion['tipo'] }}" @disabled(! $accion['habilitada']) title="{{ $accion['habilitada'] ? $detalle : $accion['motivo'] }}"><x-dynamic-component :component="'heroicon-o-'.$icono" /><strong>{{ $titulo }}</strong><small>{{ $accion['habilitada'] ? $detalle : $accion['motivo'] }}</small></button>
        @endforeach
    </div>
    @include('marcacion.partials.escaner', ['mostrarBoton' => false, 'registrarAccionDirecta' => true])
    <div class="links"><a href="{{ route('horario.show') }}"><x-heroicon-o-calendar-days />Mi horario</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form></div>
</main>
</body>
</html>
