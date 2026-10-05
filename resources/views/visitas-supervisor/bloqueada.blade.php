<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visita pendiente</title>
    <x-pwa-head />
    <x-public-theme />
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:1.5rem; background:var(--app-page); color:var(--app-text); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; }
        main { width:min(100%,25rem); padding:1.75rem; text-align:center; background:var(--app-surface); border:1px solid var(--app-border); border-radius:1rem; }
        .icono { width:3rem; height:3rem; margin:0 auto .9rem; color:var(--app-warning); }
        h1 { margin:0; font-size:1.125rem; } p { margin:.55rem 0 0; color:var(--app-muted); line-height:1.5; }
        .local { margin:1.25rem 0; padding:.875rem; border:1px solid var(--app-border); border-radius:.75rem; background:var(--app-subtle); font-weight:700; }
        a,button { display:inline-flex; justify-content:center; width:100%; min-height:2.75rem; align-items:center; border:0; border-radius:.625rem; background:var(--primary,#2563eb); color:#fff; font:inherit; font-weight:700; text-decoration:none; cursor:pointer; }
        form { margin-top:.75rem; } .salir { background:transparent; color:var(--app-muted); font-weight:500; }
    </style>
</head>
<body>
    <main>
        <x-heroicon-o-exclamation-triangle class="icono" />
        <h1>Primero registra tu salida</h1>
        <p>Tienes una visita en curso. Escanea el QR de ese local para cerrarla antes de registrar otra.</p>
        <div class="local">{{ $visita->sucursal?->nombre ?? 'Local anterior' }}</div>
        <a href="{{ route('visita-supervisor.esperando') }}">Volver a escanear</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="salir" type="submit">Cerrar sesión</button></form>
    </main>
</body>
</html>
