<?php
use VitSeo\Support\Format;
use VitSeo\Support\ReportUi;

$scoreTone = ReportUi::scoreTone($report->score);
$aiPrompt = ReportUi::aiPrompt($report->finalUrl, $report->findings);
$tabs = [
    'problemas' => 'Problemas',
    'velocidade' => 'Velocidade',
    'seo' => 'SEO',
    'geo' => 'GEO',
    'eeat' => 'E-E-A-T',
    'imagens' => 'Imagens',
    'mobile' => 'Mobile',
    'analytics' => 'Analytics',
    'subpaginas' => 'Subpáginas',
    'palavra-chave' => 'Palavra-chave',
    'semantica' => 'Semântica',
];
$targetKey = static function (string $url): string {
    $url = trim($url);
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        $url = 'https://' . $url;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return strtolower($url);
    }
    $path = $parts['path'] ?? '/';
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    return strtolower((string) $parts['host']) . $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
};
$showInformed = $targetKey($report->inputUrl) !== $targetKey($report->finalUrl);
$footerExtra = 'O resultado existe só nesta execução. Atualizar a página pode descartá-lo.';
?>
<header class="report-hero">
    <div class="wrap">
        <div class="hero-grid">
            <div>
                <p class="wordmark wordmark--light"><a href="./"><span class="mark" aria-hidden="true"></span> VitSEO</a></p>
                <p class="hero-kicker">URL analisada</p>
                <h1><?= e($report->domain) ?></h1>
                <p class="hero-url"><a href="<?= e($report->finalUrl) ?>" rel="nofollow noreferrer" target="_blank"><?= e($report->finalUrl) ?></a></p>
                <?php if ($showInformed): ?>
                    <p class="hero-meta">URL informada: <?= e($report->inputUrl) ?> · URL final: <?= e($report->finalUrl) ?></p>
                <?php endif; ?>
                <p class="hero-meta">HTTP <?= e((string) $report->httpStatus) ?> · <?= e(Format::seconds($report->durationMs / 1000)) ?> · <?= e($report->analyzedAt) ?> · 1 página · <?= e((string) $report->counts['images']) ?> imagens · <?= e((string) $report->counts['internal_links']) ?> links internos</p>
            </div>
            <div class="score-block" title="Resumo visual dos achados. Não é uma medida absoluta.">
                <?php partial('partials/gauge', [
                    'value' => Format::score($report->score),
                    'ratio' => $report->score / 10,
                    'tone' => $scoreTone,
                    'label' => 'Nota geral ' . Format::score($report->score) . ' de 10. Resumo visual, não é uma medida absoluta.',
                ]); ?>
                <p class="score-caption">Nota geral</p>
            </div>
        </div>
        <?php if ($report->httpStatus !== 200): ?>
            <p class="hero-flag">A resposta final foi HTTP <?= e((string) $report->httpStatus) ?>. O relatório descreve essa resposta.</p>
        <?php endif; ?>
    </div>
</header>

<main id="conteudo" class="report-main">
    <div class="wrap">
        <section class="category-grid" aria-label="Categorias">
            <?php foreach (ReportUi::categoryCards($report) as $card): ?>
                <article class="category-card category-card--<?= e($card['tone']) ?>">
                    <h2><?= e($card['label']) ?></h2>
                    <?php if ($card['kind'] === 'gauge'): ?>
                        <?php partial('partials/gauge', [
                            'value' => $card['value'],
                            'ratio' => $card['ratio'],
                            'tone' => $card['tone'],
                            'label' => $card['aria'],
                        ]); ?>
                    <?php else: ?>
                        <div class="status-orb status-orb--<?= e($card['tone']) ?>" role="img" aria-label="<?= e($card['aria']) ?>">
                            <?php partial('partials/icon', ['tone' => $card['tone']]); ?>
                        </div>
                    <?php endif; ?>
                    <p class="card-status">
                        <?php partial('partials/icon', ['tone' => $card['tone']]); ?>
                        <span><?= e($card['status']) ?></span>
                    </p>
                    <p class="card-extra"><?= e($card['extra']) ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="panel" data-tabs>
            <div class="tablist" role="tablist" aria-label="Seções do relatório">
                <?php $index = 0; foreach ($tabs as $id => $label): ?>
                    <button type="button" role="tab" id="tab-<?= e($id) ?>" aria-controls="panel-<?= e($id) ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>" tabindex="<?= $index === 0 ? '0' : '-1' ?>">
                        <?= e($label) ?>
                        <?php if ($id === 'problemas' && $report->urgentCount > 0): ?>
                            <span class="tab-count"><?= e((string) $report->urgentCount) ?></span>
                        <?php endif; ?>
                    </button>
                <?php $index++; endforeach; ?>
            </div>
            <?php $index = 0; foreach ($tabs as $id => $label): ?>
                <div role="tabpanel" id="panel-<?= e($id) ?>" aria-labelledby="tab-<?= e($id) ?>" <?= $index === 0 ? '' : 'hidden' ?>>
                    <?php partial('tabs/' . $id, ['report' => $report]); ?>
                </div>
            <?php $index++; endforeach; ?>
        </section>
    </div>
</main>
<div class="ai-bar">
    <div class="wrap">
        <button type="button" class="button" id="ai-adjust" <?= $aiPrompt === null ? 'disabled' : '' ?> title="<?= $aiPrompt === null ? 'Nenhum problema comprovado para enviar à IA.' : 'Copia um prompt com os problemas comprovados.' ?>">Ajustar com IA</button>
        <?php if ($aiPrompt === null): ?>
            <p class="ai-hint">Nenhum problema comprovado para enviar à IA.</p>
        <?php else: ?>
            <p class="ai-feedback" id="ai-feedback" hidden>Prompt copiado!</p>
            <script type="application/json" id="ai-prompt"><?= json_encode($aiPrompt, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?></script>
        <?php endif; ?>
    </div>
</div>
