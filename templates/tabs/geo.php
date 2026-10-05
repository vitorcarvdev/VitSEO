<?php
use VitSeo\Support\ReportUi;

$data = $report->geo;
?>
<div class="summary-band">
    <div class="status-orb status-orb--info" role="img" aria-label="GEO informativo">
        <?php partial('partials/icon', ['tone' => 'info']); ?>
    </div>
    <div>
        <p class="summary-kicker">Status</p>
        <p class="summary-title">
            <?php partial('partials/icon', ['tone' => 'info']); ?>
            <span>Informativo</span>
        </p>
        <?php partial('partials/marks', ['marks' => ReportUi::geoMarks($data)]); ?>
    </div>
</div>
<div class="callout">
    <p><?= e($data['note']) ?></p>
</div>
<h2>llms.txt</h2>
<dl class="facts">
    <?php foreach ($data['llms'] as $name => $item): ?>
        <div>
            <dt><?= e($name) ?></dt>
            <dd><?= e($item['label']) ?> · HTTP <?= e((string) $item['status']) ?><br><span class="muted"><?= e($item['url']) ?>. <?= e($item['note']) ?></span></dd>
        </div>
    <?php endforeach; ?>
</dl>

<h2>robots.txt</h2>
<p><strong><?= e($data['robots']['label']) ?></strong> · <?= e($data['robots']['url']) ?> · HTTP <?= e((string) $data['robots']['status']) ?></p>
<?php if ($data['robots']['note'] !== ''): ?><p class="muted"><?= e($data['robots']['note']) ?></p><?php endif; ?>
<?php if ($data['robots']['agents'] !== []): ?>
    <p>User-agents: <?= e(implode(', ', $data['robots']['agents'])) ?></p>
<?php endif; ?>
<?php if ($data['robots']['disallow'] !== []): ?>
    <p>Disallow encontrados, sem serem tratados como erro:</p>
    <ul>
        <?php foreach (array_slice($data['robots']['disallow'], 0, 20) as $rule): ?>
            <li><code><?= e($rule === '' ? '(vazio)' : $rule) ?></code></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($data['robots']['excerpt'] !== ''): ?>
    <pre><?= e($data['robots']['excerpt']) ?></pre>
<?php endif; ?>

<h2>Sitemap</h2>
<?php foreach ($data['sitemaps'] as $sitemap): ?>
    <article class="block">
        <p><strong><?= $sitemap['found'] ? e($sitemap['kind']) : 'Não identificado' ?></strong></p>
        <p><?= e($sitemap['url']) ?> · HTTP <?= e((string) $sitemap['status']) ?></p>
        <?php if ($sitemap['found']): ?>
            <p><?= e((string) $sitemap['loc_count']) ?> endereço(s) &lt;loc&gt; no trecho baixado. <?= $sitemap['declared'] ? 'Declarado no robots.txt.' : 'Caminho convencional.' ?></p>
        <?php endif; ?>
        <?php if ($sitemap['note'] !== ''): ?><p class="muted"><?= e($sitemap['note']) ?></p><?php endif; ?>
    </article>
<?php endforeach; ?>

<h2>HTML e conteúdo rastreável</h2>
<dl class="facts">
    <div><dt>main</dt><dd><?= $data['semantic']['main'] ? 'presente' : 'ausente' ?></dd></div>
    <div><dt>article</dt><dd><?= $data['semantic']['article'] ? 'presente' : 'ausente' ?></dd></div>
    <div><dt>nav / header / footer</dt><dd><?= $data['semantic']['nav'] ? 'nav' : 'sem nav' ?> · <?= $data['semantic']['header'] ? 'header' : 'sem header' ?> · <?= $data['semantic']['footer'] ? 'footer' : 'sem footer' ?></dd></div>
    <div><dt>Headings</dt><dd><?= e((string) $data['semantic']['headings']) ?></dd></div>
    <div><dt>Listas</dt><dd><?= e((string) $data['semantic']['lists']) ?></dd></div>
    <div><dt>Tabelas</dt><dd><?= e((string) $data['semantic']['tables']) ?></dd></div>
    <div><dt>Texto aproximado</dt><dd><?= e((string) $data['semantic']['text_length']) ?> caracteres</dd></div>
    <div><dt>JSON-LD</dt><dd><?= $data['schema_types'] !== [] ? e(implode(', ', $data['schema_types'])) : 'nenhum @type' ?></dd></div>
</dl>
