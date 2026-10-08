@php($validarAntesDeContinuar = $validarAntesDeContinuar ?? false)
@php($mostrarBoton = $mostrarBoton ?? true)
<style>
    html.mp-escaner-activo { overflow: hidden; }
    .mp-escanear { display:flex; align-items:center; justify-content:center; gap:.5rem; width:100%; min-height:3rem; margin-top:1rem; border:0; border-radius:.625rem; background:var(--primary,#2563eb); color:#fff; font:inherit; font-weight:700; cursor:pointer; }
    .mp-aviso { display:none; margin-top:.75rem; padding:.7rem .8rem; border:1px solid color-mix(in srgb,var(--app-danger,#b42318) 38%,var(--app-border,#d0d5dd)); border-radius:.625rem; background:var(--app-danger-bg,#fef2f2); color:var(--app-danger,#b42318); font-size:.8125rem; line-height:1.4; }
    .mp-overlay { display:none; position:fixed; inset:0; z-index:9999; flex-direction:column; align-items:center; justify-content:center; padding:max(1.25rem, env(safe-area-inset-top)) 1.25rem max(1.5rem, env(safe-area-inset-bottom)); background:radial-gradient(circle at 50% 18%, rgb(27 75 130 / .42), transparent 38%), #061426; color:#fff; isolation:isolate; }
    .mp-overlay.activo { display:flex; }
    .mp-overlay__heading { width:min(100%, 26rem); margin:0 0 1rem; text-align:center; }
    .mp-overlay__heading p { margin:0; color:#fff; font-size:1.125rem; font-weight:700; letter-spacing:.01em; }
    .mp-camara { position:relative; width:min(100%, 26rem); overflow:hidden; border:1px solid rgb(191 219 254 / .55); border-radius:1.25rem; background:#101828; box-shadow:0 1.5rem 3rem rgb(0 0 0 / .35); }
    .mp-overlay video { display:block; width:100%; aspect-ratio:1; object-fit:cover; }
    .mp-marco { position:absolute; inset:12%; border:2px solid #fff; border-radius:1rem; box-shadow:0 0 0 999px rgb(1 12 27 / .42); pointer-events:none; }
    .mp-estado { max-width:23rem; margin:.9rem 0 0; color:#dbeafe; font-size:.875rem; line-height:1.45; text-align:center; }
    .mp-overlay button { font:inherit; cursor:pointer; }
    .mp-cancelar { min-height:2.75rem; margin-top:1rem; padding:.6rem 1rem; border:1px solid rgb(255 255 255 / .45); border-radius:.75rem; background:rgb(255 255 255 / .08); color:#fff; }
    .mp-foto { display:inline-flex; align-items:center; justify-content:center; min-height:2.5rem; margin-top:.7rem; color:#dbeafe; font-size:.8125rem; text-decoration:underline; cursor:pointer; }
    .mp-foto input { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); clip-path:inset(50%); white-space:nowrap; }
    .mp-result { display:none; width:min(100%, 26rem); padding:1.5rem; border:1px solid rgb(191 219 254 / .4); border-radius:1.25rem; background:#0f2746; color:#f8fafc; text-align:center; box-shadow:0 1.5rem 3rem rgb(0 0 0 / .25); }
    .mp-result.activo { display:block; }
    .mp-result-icono { width:2.5rem; height:2.5rem; margin:0 auto .625rem; color:#86efac; }
    .mp-result h2 { margin:0; font-size:1.0625rem; }
    .mp-result p { margin:.35rem 0 0; color:#dbeafe; font-size:.875rem; }
    .mp-result-botones { display:grid; grid-template-columns:1fr 1fr; gap:.625rem; margin-top:.85rem; }
    .mp-result-botones button { min-height:2.75rem; border-radius:.75rem; font-weight:700; }
    .mp-continuar { border:0; background:#1677ff; color:#fff; }
    .mp-reintentar { border:1px solid rgb(255 255 255 / .3); background:transparent; color:#fff; }
    .mp-result.error .mp-result-icono { color:#fca5a5; }
</style>

<div class="mp-escaner" data-qr-scanner data-validar-antes="{{ $validarAntesDeContinuar ? '1' : '0' }}" data-ruta-validacion="{{ route('marcacion.validar-qr') }}" data-ruta-qr="{{ $rutaQr ?? '/marcar?token=' }}">
<div class="mp-aviso" data-qr-aviso-permiso>No se pudo acceder a la cámara. Revisa el permiso de cámara e inténtalo nuevamente o usa una foto del QR.</div>
@if($mostrarBoton)<button type="button" class="mp-escanear" data-qr-iniciar><x-heroicon-o-qr-code style="width:1.25rem;height:1.25rem" />{{ $textoBoton ?? 'Escanear QR de la estación' }}</button>@endif

<div class="mp-overlay" data-qr-overlay aria-live="polite" role="dialog" aria-modal="true" aria-label="Escáner QR">
    <div class="mp-overlay__heading"><p>Escanea el QR</p></div>
    <div class="mp-camara" data-qr-vista><video data-qr-video playsinline muted></video><div class="mp-marco"></div></div>
    <div class="mp-estado" data-qr-estado>Apunta la cámara al QR actual de la estación</div>
    <label class="mp-foto" data-qr-foto>Usar foto del QR<input data-qr-archivo type="file" accept="image/*" capture="environment"></label>
    <button type="button" class="mp-cancelar" data-qr-cancelar>Cancelar</button>
    <section class="mp-result" data-qr-resultado><x-heroicon-s-check-circle class="mp-result-icono" data-qr-icono /><h2 data-qr-titulo>QR validado</h2><p data-qr-detalle></p><div class="mp-result-botones"><button type="button" class="mp-reintentar" data-qr-reintentar>Escanear nuevamente</button><button type="button" class="mp-continuar" data-qr-continuar>{{ $textoContinuar ?? 'Continuar' }}</button></div></section>
</div>
</div>

@vite('resources/js/qr-scanner.js')
