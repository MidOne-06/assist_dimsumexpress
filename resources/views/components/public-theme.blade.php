{{-- Tema base para las pantallas públicas y PWA.
     La preferencia proviene exclusivamente de prefers-color-scheme, es decir,
     del ajuste claro/oscuro del dispositivo. --}}
<style>
    :root {
        color-scheme: light dark;
        --app-page: #f8fafc;
        --app-surface: #ffffff;
        --app-subtle: #f1f5f9;
        --app-text: #172033;
        --app-muted: #667085;
        --app-border: #d0d5dd;
        --app-success: #15803d;
        --app-success-bg: #f0fdf4;
        --app-warning: #b54708;
        --app-warning-bg: #fffaeb;
        --app-danger: #b42318;
        --app-danger-bg: #fef3f2;
        --app-info: #175cd3;
        --app-info-bg: #eff8ff;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --app-page: #101828;
            --app-surface: #1d2939;
            --app-subtle: #182230;
            --app-text: #f8fafc;
            --app-muted: #cbd5e1;
            --app-border: #475467;
            --app-success: #86efac;
            --app-success-bg: #13361f;
            --app-warning: #facc15;
            --app-warning-bg: #3b2f09;
            --app-danger: #fca5a5;
            --app-danger-bg: #451a1a;
            --app-info: #93c5fd;
            --app-info-bg: #172554;
        }
    }
</style>
