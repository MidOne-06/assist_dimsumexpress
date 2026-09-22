{{-- Escáner de QR embebido en la misma vista (no abre ninguna app externa).
     Usa la API nativa BarcodeDetector del navegador; si no está disponible,
     cae de vuelta a la instrucción de usar la cámara del celular. --}}
<style>
    button.mp-escanear { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.85rem; border: none; border-radius: 0.6rem; font-size: 1rem; font-weight: 600; color: #fff; background: #2563eb; cursor: pointer; margin-bottom: 0.75rem; }
    button.mp-escanear:hover { background: #1d4ed8; }
    .mp-aviso { display: none; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.65rem 0.85rem; border-radius: 0.5rem; font-size: 0.8rem; margin-bottom: 1rem; text-align: left; }

    .mp-overlay { display: none; position: fixed; inset: 0; background: #000; z-index: 50; flex-direction: column; align-items: center; justify-content: center; }
    .mp-overlay.activo { display: flex; }
    .mp-overlay video { width: 100%; max-width: 26rem; aspect-ratio: 1 / 1; object-fit: cover; border-radius: 0.75rem; }
    .mp-overlay .mp-marco { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: min(70vw, 18rem); height: min(70vw, 18rem); border: 3px solid #fff; border-radius: 1rem; box-shadow: 0 0 0 999px rgba(0,0,0,0.45); pointer-events: none; }
    .mp-overlay .mp-estado { color: #fff; margin-top: 1.25rem; font-size: 0.85rem; text-align: center; padding: 0 1.5rem; }
    .mp-overlay button.mp-cancelar { margin-top: 1.5rem; background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.4); border-radius: 0.5rem; padding: 0.6rem 1.5rem; font-size: 0.9rem; cursor: pointer; }
</style>

<div class="mp-aviso" id="mp-aviso-sin-soporte">
    Tu navegador no permite escanear directamente aquí. Abre la app de cámara de tu celular y apunta al código QR de la pantalla; se abrirá esta misma página automáticamente.
</div>
<div class="mp-aviso" id="mp-aviso-sin-permiso">
    No se pudo acceder a la cámara. Revisa que le hayas dado permiso de cámara a este sitio en la configuración de tu navegador.
</div>

<button type="button" class="mp-escanear" id="mp-btn-escanear">
    <x-heroicon-o-qr-code style="width: 1.25rem; height: 1.25rem;" />
    {{ $textoBoton ?? 'Escanear código QR' }}
</button>

<div class="mp-overlay" id="mp-overlay-camara">
    <div style="position: relative;">
        <video id="mp-video-camara" playsinline muted></video>
        <div class="mp-marco"></div>
    </div>
    <div class="mp-estado" id="mp-estado-camara">Apunta la cámara al código QR de la pantalla</div>
    <button type="button" class="mp-cancelar" id="mp-btn-cancelar" style="display: flex; align-items: center; gap: 0.35rem;">
        <x-heroicon-o-x-mark style="width: 1rem; height: 1rem;" />
        Cancelar
    </button>
</div>

<script>
    (function () {
        const btnEscanear = document.getElementById('mp-btn-escanear');
        const btnCancelar = document.getElementById('mp-btn-cancelar');
        const overlay = document.getElementById('mp-overlay-camara');
        const video = document.getElementById('mp-video-camara');
        const estado = document.getElementById('mp-estado-camara');
        const avisoSinSoporte = document.getElementById('mp-aviso-sin-soporte');
        const avisoSinPermiso = document.getElementById('mp-aviso-sin-permiso');
        const rutaQrPermitida = @js($rutaQr ?? '/marcar?token=');

        let stream = null;
        let escaneando = false;

        function detenerCamara() {
            escaneando = false;
            overlay.classList.remove('activo');
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
                stream = null;
            }
        }

        async function iniciarEscaneo() {
            avisoSinSoporte.style.display = 'none';
            avisoSinPermiso.style.display = 'none';

            if (!('BarcodeDetector' in window)) {
                avisoSinSoporte.style.display = 'block';
                return;
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                });
            } catch (e) {
                avisoSinPermiso.style.display = 'block';
                return;
            }

            video.srcObject = stream;
            await video.play();
            overlay.classList.add('activo');
            estado.textContent = 'Apunta la cámara al código QR de la pantalla';

            const detector = new BarcodeDetector({ formats: ['qr_code'] });
            escaneando = true;

            const loop = async () => {
                if (!escaneando) return;

                try {
                    const codigos = await detector.detect(video);

                    if (codigos.length > 0) {
                        const valor = codigos[0].rawValue || '';

                        // Solo navega si el QR realmente apunta al marcado de este
                        // sistema -- evita que un QR ajeno/malicioso redirija a
                        // otro sitio.
                        if (valor.includes(rutaQrPermitida)) {
                            estado.textContent = 'Código detectado, registrando...';
                            detenerCamara();
                            window.location.href = valor;
                            return;
                        }
                    }
                } catch (e) {
                    // Ignora errores puntuales de detección y sigue intentando.
                }

                requestAnimationFrame(loop);
            };

            requestAnimationFrame(loop);
        }

        btnEscanear.addEventListener('click', iniciarEscaneo);
        btnCancelar.addEventListener('click', detenerCamara);
        window.addEventListener('pagehide', detenerCamara);
    })();
</script>
