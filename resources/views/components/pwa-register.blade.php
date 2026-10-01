<x-pwa-ios-install />

<script>
    (() => {
        let deferredInstall = null;
        const installButtons = () => document.querySelectorAll('[data-pwa-install]');
        const iosInstallDialog = document.querySelector('[data-pwa-ios-dialog]');
        const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        const isSafari = /^((?!chrome|android|crios|fxios).)*safari/i.test(navigator.userAgent);

        const showInstallButtons = () => installButtons().forEach((button) => button.hidden = false);
        const hideInstallButtons = () => installButtons().forEach((button) => button.hidden = true);
        const openIosInstallGuide = () => {
            if (! iosInstallDialog) return;

            const message = iosInstallDialog.querySelector('[data-pwa-ios-browser-message]');
            if (message) {
                message.textContent = isSafari
                    ? 'Añade el acceso a la pantalla de inicio desde el menú de Safari.'
                    : 'Para instalarla, abre este enlace en Safari y añádela a la pantalla de inicio.';
            }

            iosInstallDialog.hidden = false;
            iosInstallDialog.querySelector('[data-pwa-ios-close]')?.focus();
        };

        const openIosInstallFlow = async () => {
            if (isSafari && typeof navigator.share === 'function') {
                try {
                    await navigator.share({
                        title: document.title,
                        url: window.location.href,
                    });

                    return;
                } catch (_) {
                    // Safari may reject the share request when it is unavailable or cancelled.
                }
            }

            openIosInstallGuide();
        };

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register(@js(route('pwa.service-worker')), { scope: '/' }).catch(() => {}));
        }

        if (isIos && ! isStandalone) {
            showInstallButtons();
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredInstall = event;
            showInstallButtons();
        });

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-pwa-install]');
            if (button && isIos && ! isStandalone) {
                await openIosInstallFlow();
                return;
            }

            if (! button || ! deferredInstall) return;

            await deferredInstall.prompt();
            deferredInstall = null;
            hideInstallButtons();
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-pwa-ios-close]')) iosInstallDialog && (iosInstallDialog.hidden = true);
        });

        window.addEventListener('appinstalled', hideInstallButtons);
    })();
</script>
