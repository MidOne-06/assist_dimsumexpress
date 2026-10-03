<div class="pwa-ios-install-dialog" data-pwa-ios-dialog role="dialog" aria-modal="true" aria-labelledby="pwa-ios-install-title" hidden>
    <section class="pwa-ios-install-card">
        <div class="pwa-ios-install-heading">
            <span class="pwa-ios-install-symbol" aria-hidden="true"><x-heroicon-o-device-phone-mobile /></span>
            <div>
                <h2 id="pwa-ios-install-title">Instalar en iPhone</h2>
                <p data-pwa-ios-browser-message>Abre esta página en Safari para añadirla a tu pantalla de inicio.</p>
            </div>
            <button type="button" class="pwa-ios-install-close" data-pwa-ios-close aria-label="Cerrar"><x-heroicon-o-x-mark /></button>
        </div>

        <ol class="pwa-ios-install-steps" aria-label="Pasos de instalación">
            <li><x-heroicon-o-arrow-up-on-square aria-hidden="true" /><span>Toca <strong>Compartir</strong> en Safari.</span></li>
            <li><x-heroicon-o-plus-circle aria-hidden="true" /><span>Elige <strong>Agregar a pantalla de inicio</strong>.</span></li>
            <li><x-heroicon-o-check-circle aria-hidden="true" /><span>Confirma con <strong>Agregar</strong>.</span></li>
        </ol>

        <button type="button" class="pwa-ios-install-action" data-pwa-ios-close>Entendido</button>
    </section>
</div>

<style>
    .pwa-ios-install-dialog { position: fixed; z-index: 1000; inset: 0; display: grid; place-items: center; padding: 1rem; background: rgb(15 23 42 / .55); color: var(--ink, var(--app-text, #172033)); backdrop-filter: blur(2px); }
    .pwa-ios-install-dialog[hidden] { display: none; }
    .pwa-ios-install-card { width: min(100%, 25rem); padding: 1.25rem; border:1px solid var(--line, var(--app-border, #d0d5dd)); border-radius: 1rem; background: var(--surface, var(--app-surface, #fff)); box-shadow: 0 1.5rem 4rem rgb(15 23 42 / .3); }
    .pwa-ios-install-heading { display: grid; grid-template-columns: auto 1fr auto; align-items: start; gap: .75rem; }
    .pwa-ios-install-symbol { display: grid; width: 2.5rem; height: 2.5rem; place-items: center; border-radius: .75rem; background: color-mix(in srgb, var(--primary, #f59e0b) 13%, transparent); color: var(--primary, #f59e0b); }
    .pwa-ios-install-symbol svg { width: 1.25rem; height: 1.25rem; }
    .pwa-ios-install-heading h2 { margin: .1rem 0 .2rem; font-size: 1rem; line-height: 1.35; }
    .pwa-ios-install-heading p { margin: 0; color: var(--muted, var(--app-muted, #475467)); font-size: .8125rem; line-height: 1.45; }
    .pwa-ios-install-close { display: grid; width: 2rem; height: 2rem; place-items: center; border: 0; border-radius: .5rem; background: transparent; color: var(--muted, var(--app-muted, #475467)); cursor: pointer; }
    .pwa-ios-install-close:hover, .pwa-ios-install-close:focus-visible { background: color-mix(in srgb, var(--primary, #f59e0b) 10%, transparent); color: var(--primary, #f59e0b); outline: 0; }
    .pwa-ios-install-close svg { width: 1.125rem; height: 1.125rem; }
    .pwa-ios-install-steps { display: grid; gap: .75rem; margin: 1.25rem 0; padding: 0; list-style: none; }
    .pwa-ios-install-steps li { display: grid; grid-template-columns: 1.25rem 1fr; gap: .625rem; align-items: center; min-height: 2.5rem; padding: .5rem .625rem; border: 1px solid var(--field-border, var(--app-border, #d0d5dd)); border-radius: .625rem; color: var(--muted, var(--app-muted, #475467)); font-size: .8125rem; line-height: 1.35; }
    .pwa-ios-install-steps svg { width: 1.125rem; height: 1.125rem; color: var(--primary, #f59e0b); }
    .pwa-ios-install-steps strong { color: var(--ink, var(--app-text, #172033)); }
    .pwa-ios-install-action { width: 100%; min-height: 2.625rem; border: 0; border-radius: .625rem; background: var(--primary, #f59e0b); color: #fff; font: inherit; font-size: .875rem; font-weight: 700; cursor: pointer; }
    .pwa-ios-install-action:focus-visible { outline: 3px solid color-mix(in srgb, var(--primary, #f59e0b) 35%, transparent); outline-offset: 2px; }
</style>
