<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmar {{ $accion === 'salida' ? 'salida' : 'ingreso' }}</title>
    <x-pwa-head />
    <x-public-theme />
    <style>
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:var(--app-page); color:var(--app-text); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; padding:1.5rem; }
        main { width:min(100%,24rem); padding:1.5rem; text-align:center; background:var(--app-surface); border:1px solid var(--app-border); border-radius:1rem; }
        .icono { width:3rem; height:3rem; margin:0 auto .875rem; color:var(--app-info); }
        h1 { margin:0; font-size:1.125rem; }
        p { margin:.5rem 0 0; color:var(--app-muted); font-size:.875rem; line-height:1.5; }
        .local { margin:1.25rem 0; padding:.875rem; border:1px solid var(--app-border); border-radius:.75rem; background:var(--app-subtle); }
        .local strong,.local span { display:block; }
        .local span { margin-top:.15rem; color:var(--app-muted); font-size:.8125rem; }
        button { width:100%; min-height:2.875rem; border:0; border-radius:.625rem; background:var(--primary,#2563eb); color:#fff; font:inherit; font-weight:700; cursor:pointer; }
        .salir { display:inline-flex; margin-top:1rem; color:var(--app-muted); font-size:.8125rem; text-decoration:none; }
    </style>
</head>
<body>
    <main>
        <x-heroicon-o-map-pin class="icono" />
        <h1>Confirmar {{ $accion === 'salida' ? 'salida' : 'ingreso' }}</h1>
        <p>{{ $accion === 'salida' ? 'Confirma el cierre de tu visita en este local.' : 'Confirma tu llegada a este local.' }}</p>
        <div class="local"><strong>{{ $sucursal->nombre }}</strong>@if ($puntoVenta)<span>{{ $puntoVenta->nombre }}</span>@endif</div>
        <form method="POST" action="{{ route('visita-supervisor.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $qrToken->token }}">
            <input type="hidden" name="accion" value="{{ $accion }}">
            <button type="submit">{{ $accion === 'salida' ? 'Registrar salida' : 'Registrar ingreso' }}</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="salir" style="all:unset;cursor:pointer">Cerrar sesión</button></form>
    </main>
</body>
</html>
