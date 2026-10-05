<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= e($title ?? 'VitSEO') ?> · VitSEO</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/app.css">
    <noscript><style>[role="tabpanel"]{display:block !important}</style></noscript>
</head>
<body>
    <a class="skip" href="#conteudo">Ir para o conteúdo</a>
    <?php include $templateFile; ?>
    <footer class="site-footer">
        <div class="wrap">
            <p>VitSEO. Ferramenta interna de auditoria SEO baseada em evidências.</p>
            <?php if (!empty($footerExtra)): ?><p class="fine"><?= e($footerExtra) ?></p><?php endif; ?>
        </div>
    </footer>
    <div class="overlay" id="overlay">
        <div class="overlay-card">
            <span class="spinner" aria-hidden="true"></span>
            <p>Analisando a página…</p>
            <p class="muted">Coletando HTML e headers. Isso pode levar alguns segundos.</p>
        </div>
    </div>
    <script src="assets/js/app.js" defer></script>
</body>
</html>
