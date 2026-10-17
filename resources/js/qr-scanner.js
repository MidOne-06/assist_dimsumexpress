import { BrowserQRCodeReader } from '@zxing/browser';

document.querySelectorAll('[data-qr-scanner]').forEach((root) => {
    // Cada pantalla mantiene su propio lector. Así una vista restaurada por
    // la PWA no comparte una sesión de cámara anterior con otra pantalla.
    const reader = new BrowserQRCodeReader();
    const validarAntes = root.dataset.validarAntes === '1';
    const iniciarBoton = root.querySelector('[data-qr-iniciar]');
    const cancelar = root.querySelector('[data-qr-cancelar]');
    const reintentar = root.querySelector('[data-qr-reintentar]');
    const continuar = root.querySelector('[data-qr-continuar]');
    const overlay = root.querySelector('[data-qr-overlay]');
    const video = root.querySelector('[data-qr-video]');
    const estado = root.querySelector('[data-qr-estado]');
    const avisoPermiso = root.querySelector('[data-qr-aviso-permiso]');
    const vista = root.querySelector('[data-qr-vista]');
    const foto = root.querySelector('[data-qr-foto]');
    const archivo = root.querySelector('[data-qr-archivo]');
    const reintentarCamara = root.querySelector('[data-qr-reintentar-camara]');
    const resultado = root.querySelector('[data-qr-resultado]');
    const titulo = root.querySelector('[data-qr-titulo]');
    const detalle = root.querySelector('[data-qr-detalle]');
    const rutaValidacion = root.dataset.rutaValidacion;
    const rutaPermitida = new URL(root.dataset.rutaQr, window.location.origin);

    // La pantalla de inicio usa un efecto de vidrio que crea un nuevo
    // contenedor para elementos fijos. El lector debe vivir al nivel del
    // documento para cubrir realmente toda la pantalla al abrir la cámara.
    document.body.append(overlay);

    let controles = null;
    let procesando = false;
    let destino = null;
    let token = null;

    const mensajeErrorCamara = (error) => {
        if (!window.isSecureContext) {
            return 'La cámara solo funciona desde la aplicación abierta con conexión segura. Abre nuevamente el enlace oficial.';
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            return 'Este navegador no permite abrir la cámara aquí. Abre la aplicación en Safari o Chrome, o usa una foto del QR.';
        }

        switch (error?.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
            case 'SecurityError':
                return 'El permiso de cámara está bloqueado. Actívalo para esta aplicación desde los ajustes del dispositivo y vuelve a intentar.';
            case 'NotFoundError':
            case 'DevicesNotFoundError':
                return 'No se detectó una cámara disponible en este dispositivo. Usa una foto del QR.';
            case 'NotReadableError':
            case 'TrackStartError':
                return 'La cámara está siendo usada por otra aplicación. Ciérrala e inténtalo nuevamente.';
            case 'OverconstrainedError':
                return 'No se pudo seleccionar la cámara del dispositivo. Inténtalo nuevamente o usa una foto del QR.';
            default:
                return 'No se pudo abrir la cámara. Inténtalo nuevamente o usa una foto clara del QR.';
        }
    };

    const detener = () => {
        controles?.stop();
        controles = null;
        video.srcObject?.getTracks?.().forEach((track) => track.stop());
        video.srcObject = null;
    };

    const restaurar = () => {
        resultado.classList.remove('activo', 'error');
        vista.style.display = 'block';
        estado.style.display = 'block';
        foto.style.display = 'inline-flex';
        cancelar.style.display = 'inline-block';
        reintentarCamara.style.display = 'none';
        estado.textContent = 'Apunta la cámara al QR actual de la estación';
    };

    const mostrarProblemaCamara = (error) => {
        detener();
        procesando = false;
        vista.style.display = 'none';
        estado.style.display = 'block';
        foto.style.display = 'inline-flex';
        cancelar.style.display = 'inline-block';
        reintentarCamara.style.display = 'inline-flex';
        estado.textContent = mensajeErrorCamara(error);

        // Antes se cerraba el overlay y se ocultaba la alternativa de foto.
        // Mantenerlo abierto permite resolver la incidencia desde el celular.
        avisoPermiso.style.display = 'none';
    };

    const cerrar = () => {
        procesando = false;
        destino = null;
        token = null;
        detener();
        restaurar();
        overlay.classList.remove('activo');
        document.documentElement.classList.remove('mp-escaner-activo');
    };

    const mostrarResultado = (ok, mensaje = '') => {
        detener();
        procesando = true;
        vista.style.display = 'none';
        estado.style.display = 'none';
        foto.style.display = 'none';
        cancelar.style.display = 'none';
        resultado.classList.add('activo');
        resultado.classList.toggle('error', !ok);
        titulo.textContent = ok ? 'QR escaneado correctamente' : 'No se pudo validar el QR';
        detalle.textContent = ok ? '' : mensaje;
        continuar.style.display = ok ? 'inline-block' : 'none';
    };

    const leerDestino = (valor) => {
        try {
            const leido = new URL(valor, window.location.origin);
            if (leido.origin !== window.location.origin || leido.pathname !== rutaPermitida.pathname) return null;

            const codigo = leido.searchParams.get('token');

            return codigo ? { token: codigo, url: leido.toString() } : null;
        } catch (_) {
            return null;
        }
    };

    const validar = async (dato) => {
        if (!validarAntes) {
            destino = dato.url;
            token = dato.token;
            mostrarResultado(true);
            return;
        }

        try {
            const respuesta = await fetch(`${rutaValidacion}?token=${encodeURIComponent(dato.token)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const cuerpo = await respuesta.json();

            if (!respuesta.ok || !cuerpo.confirmado) {
                mostrarResultado(false, cuerpo.mensaje || 'El QR no se pudo validar.');
                return;
            }

            destino = dato.url;
            token = dato.token;
            mostrarResultado(true);
        } catch (_) {
            mostrarResultado(false, 'No se pudo validar el QR. Verifica tu conexión e inténtalo nuevamente.');
        }
    };

    const procesarLectura = async (valor) => {
        if (procesando) return;

        const dato = leerDestino(valor);
        if (!dato) {
            estado.textContent = 'Este QR no se puede usar para marcar.';
            return;
        }

        procesando = true;
        estado.textContent = 'Validando QR…';
        await validar(dato);
    };

    const iniciar = async () => {
        avisoPermiso.style.display = 'none';
        detener();
        restaurar();
        procesando = false;
        overlay.classList.add('activo');
        document.documentElement.classList.add('mp-escaner-activo');

        try {
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                mostrarProblemaCamara();
                return;
            }

            controles = await reader.decodeFromConstraints(
                { video: { facingMode: { ideal: 'environment' } }, audio: false },
                video,
                (lectura) => {
                    if (lectura) procesarLectura(lectura.getText());
                },
            );
        } catch (error) {
            mostrarProblemaCamara(error);
        }
    };

    const leerArchivo = async () => {
        const seleccionado = archivo.files?.[0];
        if (!seleccionado) return;

        detener();
        let url = null;

        try {
            procesando = true;
            estado.textContent = 'Leyendo el QR…';
            url = URL.createObjectURL(seleccionado);
            const lectura = await reader.decodeFromImageUrl(url);
            procesando = false;
            await procesarLectura(lectura.getText());
        } catch (_) {
            procesando = false;
            estado.textContent = 'No se encontró un QR legible. Toma otra foto enfocada al código.';
        } finally {
            if (url) URL.revokeObjectURL(url);
            archivo.value = '';
        }
    };

    iniciarBoton?.addEventListener('click', iniciar);
    cancelar?.addEventListener('click', cerrar);
    reintentar?.addEventListener('click', iniciar);
    reintentarCamara?.addEventListener('click', iniciar);
    archivo?.addEventListener('change', leerArchivo);
    continuar?.addEventListener('click', () => {
        if (destino) window.location.assign(destino);
    });
    window.addEventListener('pagehide', detener);
});
