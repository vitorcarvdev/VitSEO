<main class="home" id="conteudo">
    <div class="wrap home-wrap">
        <p class="wordmark"><span class="mark" aria-hidden="true"></span> VitSEO</p>
        <p class="eyebrow">Auditoria SEO baseada em evidências</p>
        <h1>Analise um site</h1>
        <p class="lede">Auditoria técnica, SEO e semântica baseada em evidências reais.</p>

        <?php if (!empty($error)): ?>
            <div class="alert" role="alert">
                <strong><?= e($error['title']) ?></strong>
                <p><?= e($error['message']) ?></p>
            </div>
        <?php endif; ?>

        <form class="audit-form" id="audit-form" method="post" action="">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <label for="url">URL do site</label>
            <div class="form-row">
                <input id="url" name="url" type="text" inputmode="url" spellcheck="false" maxlength="2000" placeholder="https://exemplo.com.br/" value="<?= e($urlValue ?? '') ?>" required>
                <button type="submit">Analisar site</button>
            </div>
        </form>
        <p class="fine">Nenhum dado da análise é armazenado.</p>
    </div>
</main>
