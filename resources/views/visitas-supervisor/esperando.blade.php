<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrar visita</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#f3f4f6; color:#111827; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; padding:1.5rem; }
        main { width:min(100%,24rem); background:#fff; border-radius:1rem; padding:2rem 1.75rem; box-shadow:0 10px 25px rgba(0,0,0,.06); text-align:center; }
        h1 { margin:0 0 .5rem; font-size:1.15rem; }
        p { margin:0 0 1.4rem; color:#6b7280; font-size:.875rem; line-height:1.5; }
        .salir { color:#6b7280; font-size:.8rem; text-decoration:none; }
    </style>
</head>
<body>
    <main>
        <x-heroicon-o-map-pin style="width:2.75rem;height:2.75rem;color:#2563eb;margin:0 auto .75rem;display:block;" />
        <h1>Registrar visita</h1>
        <p>Escanea el QR de visita del local asignado.</p>

        @include('marcacion.partials.escaner', [
            'rutaQr' => '/visitas-supervisor?token=',
            'textoBoton' => 'Escanear QR de visita',
        ])

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" style="all:unset;cursor:pointer" class="salir">Cerrar sesión</button>
        </form>
    </main>
</body>
</html>
