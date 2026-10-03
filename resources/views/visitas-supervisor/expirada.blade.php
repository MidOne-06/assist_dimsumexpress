<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR vencido</title>
    <x-public-theme />
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:var(--app-page); color:var(--app-text); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; padding:1.5rem; text-align:center; }
        main { width:min(100%,26rem); background:var(--app-surface); border:1px solid var(--app-border); border-radius:1.25rem; padding:2.5rem 2rem; }
        .icono { display:inline-grid; place-items:center; width:4rem; height:4rem; border-radius:999px; background:var(--app-warning-bg); color:var(--app-warning); font-size:2rem; }
        h1 { margin:1.25rem 0 .5rem; font-size:1.4rem; }
        p { margin:.35rem 0; color:var(--app-muted); line-height:1.5; }
    </style>
</head>
<body>
    <main>
        <div class="icono">!</div>
        <h1>Código QR vencido</h1>
        <p>Vuelve a escanear el código que muestra la estación.</p>
    </main>
</body>
</html>
