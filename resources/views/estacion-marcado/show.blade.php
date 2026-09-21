<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marcar asistencia — {{ $sucursal->nombre }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #111827; color: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; padding: 2rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 0.25rem; }
        p.sub { margin: 0 0 2rem; color: #9ca3af; font-size: 1rem; }
        .qr-wrap { background: #fff; padding: 1.25rem; border-radius: 1.25rem; box-shadow: 0 20px 45px rgba(0,0,0,0.35); }
        .qr-wrap img { display: block; width: 20rem; height: 20rem; max-width: 60vw; max-height: 60vw; }
        .reloj { margin-top: 2rem; font-size: 2.25rem; font-weight: 700; letter-spacing: 0.05em; }
        .barra { margin-top: 1rem; width: 20rem; max-width: 60vw; height: 0.4rem; background: #374151; border-radius: 999px; overflow: hidden; }
        .barra-fill { height: 100%; background: #22c55e; width: 100%; transition: width 1s linear; }
        .instrucciones { margin-top: 1.5rem; max-width: 26rem; color: #d1d5db; font-size: 0.9rem; line-height: 1.5; }
    </style>
</head>
<body>
    <h1>{{ $sucursal->nombre }}</h1>
    <p class="sub">{{ $puntoVenta?->nombre ?? 'Escanea el código para marcar tu asistencia' }}</p>

    <div class="qr-wrap">
        <img id="qr-imagen" src="" alt="Código QR de marcado">
    </div>

    <div class="barra"><div class="barra-fill" id="barra-fill"></div></div>

    <div class="reloj" id="reloj"></div>

    <p class="instrucciones">
        Abre la cámara de tu celular, escanea el código y sigue las instrucciones para registrar tu entrada,
        salida o refrigerio. El código cambia cada pocos segundos por seguridad.
    </p>

    @php
        $rutaToken = "/estacion-marcado/{$sucursal->id}";

        if ($puntoVenta) {
            $rutaToken .= "/{$puntoVenta->id}";
        }

        $urlTokenConClave = url("{$rutaToken}/token") . '?clave=' . urlencode($clave);
    @endphp
    <script>
        const urlToken = @json($urlTokenConClave);
        const vigenciaSegundos = @json($vigenciaSegundos);
        const imgQr = document.getElementById('qr-imagen');
        const barraFill = document.getElementById('barra-fill');
        let restante = vigenciaSegundos;
        let contador = null;

        function actualizarReloj() {
            document.getElementById('reloj').textContent = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        setInterval(actualizarReloj, 1000);
        actualizarReloj();

        function iniciarBarra() {
            restante = vigenciaSegundos;
            barraFill.style.transition = 'none';
            barraFill.style.width = '100%';
            requestAnimationFrame(() => {
                barraFill.style.transition = `width ${vigenciaSegundos}s linear`;
                barraFill.style.width = '0%';
            });
        }

        async function refrescarQr() {
            try {
                const respuesta = await fetch(urlToken, { headers: { 'Accept': 'application/json' } });
                const datos = await respuesta.json();
                imgQr.src = datos.qr;
                iniciarBarra();
            } catch (e) {
                // Reintenta en el siguiente ciclo sin romper la pantalla.
            }
        }

        refrescarQr();
        setInterval(refrescarQr, vigenciaSegundos * 1000);
    </script>
</body>
</html>
