<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escanea nuevamente</title>
    <x-pwa-head />
    <x-public-theme />
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:1.5rem; background:var(--app-page); color:var(--app-text); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; }
        main { width:min(100%,25rem); padding:1.75rem; text-align:center; background:var(--app-surface); border:1px solid var(--app-border); border-radius:1rem; }
        .icono { width:3rem; height:3rem; margin:0 auto .9rem; color:var(--app-warning); }
        h1 { margin:0; font-size:1.125rem; } p { margin:.55rem 0 1.25rem; color:var(--app-muted); line-height:1.5; }
        a { display:inline-flex; justify-content:center; width:100%; min-height:2.75rem; align-items:center; border-radius:.625rem; background:var(--primary,#2563eb); color:#fff; font-weight:700; text-decoration:none; }
    </style>
</head>
<body>
    <main>
        <x-heroicon-o-arrow-path class="icono" />
        <h1>Escanea nuevamente</h1>
        <p>{{ $motivo }}</p>
        <a href="{{ route('visita-supervisor.esperando') }}">Abrir escáner</a>
    </main>
</body>
</html>
