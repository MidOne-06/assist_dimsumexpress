<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marcación — {{ $apariencia->nombre() }}</title><link rel="icon" href="{{ $apariencia->iconoUrl() }}"><x-pwa-head /><x-public-theme />
    <style>
        :root{--primary:{{ $apariencia->colorPrimario() }};--page:var(--app-page);--surface:var(--app-surface);--soft:var(--app-subtle);--ink:var(--app-text);--muted:var(--app-muted);--line:var(--app-border)}
        *{box-sizing:border-box}body{min-height:100dvh;margin:0;display:grid;place-items:center;padding:1rem;background:var(--page);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}.shell{width:min(100%,31rem);padding:1.125rem;background:var(--surface);border:1px solid var(--line);border-radius:.875rem}.brand{display:flex;justify-content:space-between;align-items:center;padding-bottom:.875rem;border-bottom:1px solid var(--line)}.brand img{height:2.1rem;max-width:8rem;object-fit:contain}.brand span{font-size:.75rem;color:var(--muted);font-weight:700;letter-spacing:.05em;text-transform:uppercase}.identity{padding:.875rem 0 .25rem}.identity h1{margin:0;font-size:1.125rem;line-height:1.3}.identity p{margin:.2rem 0 0;color:var(--muted);font-size:.875rem;line-height:1.45}.links{display:flex;justify-content:space-between;margin-top:1rem}.links a,.links button{display:inline-flex;align-items:center;gap:.35rem;min-height:2.75rem;border:0;background:none;padding:0;color:var(--muted);font:inherit;font-size:.8125rem;text-decoration:none;cursor:pointer}.links svg{width:1rem;height:1rem}@media(max-width:640px){body{display:block;padding:0}.shell{min-height:100dvh;width:100%;padding:1.25rem 1rem calc(1.25rem + env(safe-area-inset-bottom));border:0;border-radius:0}}
    </style>
</head>
<body>
<main class="shell">
    <header class="brand"><img src="{{ $apariencia->logoUrl() }}" alt="{{ $apariencia->nombre() }}"><span>Marcación</span></header>
    <section class="identity"><h1>Hola, {{ $colaborador->nombre_completo }}</h1><p>Escanea el QR</p></section>
    @include('marcacion.partials.escaner', ['mostrarBoton' => true, 'registrarAccionDirecta' => true, 'textoBoton' => 'Escanear QR'])
    <div class="links"><a href="{{ route('horario.show') }}"><x-heroicon-o-calendar-days />Mi horario</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form></div>
</main>
</body>
</html>
