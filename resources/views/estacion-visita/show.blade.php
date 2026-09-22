<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visita de supervisión — {{ $sucursal->nombre }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#111827; color:#f9fafb; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; padding:2rem; text-align:center; }
        h1 { font-size:1.5rem; margin:0 0 .25rem; }
        .sub { margin:0 0 2rem; color:#9ca3af; font-size:1rem; }
        .qr-wrap { background:#fff; padding:1.25rem; border-radius:1.25rem; box-shadow:0 20px 45px rgba(0,0,0,.35); }
        .qr-wrap img { display:block; width:20rem; height:20rem; max-width:60vw; max-height:60vw; }
        .reloj { margin-top:2rem; font-size:2.25rem; font-weight:700; letter-spacing:.05em; }
        .instrucciones { margin-top:1.5rem; max-width:26rem; color:#d1d5db; font-size:.9rem; line-height:1.5; }
    </style>
</head>
<body>
    <h1>{{ $sucursal->nombre }}</h1>
    <p class="sub">{{ $puntoVenta?->nombre ?? 'Visita de supervisión' }}</p>
    <div class="qr-wrap"><img src="{{ $qr }}" alt="Código QR de visita de supervisión"></div>
    <div class="reloj" id="reloj"></div>
    <p class="instrucciones">La supervisora debe iniciar sesión y escanear este código al visitar el local.</p>
    <script>
        const actualizarReloj = () => document.getElementById('reloj').textContent = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        actualizarReloj(); setInterval(actualizarReloj, 1000);
    </script>
</body>
</html>
