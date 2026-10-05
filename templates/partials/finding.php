<article class="finding finding--<?= e($finding->severity) ?> finding--<?= e($finding->classification) ?>">
    <header class="finding-head">
        <h3><?= e($finding->title) ?></h3>
        <div class="pills">
            <span class="pill pill--<?= e($finding->severity) ?>"><?= e(label_severity($finding->severity)) ?></span>
            <span class="pill pill--<?= e($finding->classification) ?>"><?= e(label_classification($finding->classification)) ?></span>
        </div>
    </header>
    <p class="finding-kicker">Evidência</p>
    <p class="evidence"><?= e($finding->evidence) ?></p>
    <p class="finding-kicker">O que isso significa</p>
    <p class="finding-copy"><?= e($finding->explanation) ?></p>
    <?php if ($finding->recommendation): ?>
        <p class="finding-kicker">Recomendação</p>
        <p class="finding-copy"><?= e($finding->recommendation) ?></p>
    <?php endif; ?>
</article>
