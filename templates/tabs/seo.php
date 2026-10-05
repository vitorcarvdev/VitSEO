<?php
use VitSeo\Support\Format;
use VitSeo\Support\ReportUi;

$data = $report->seo;
$seoScore = null;
foreach ($report->categories as $card) {
    if ($card['label'] === 'SEO') {
        $seoScore = (float) $card['score'];
    }
}
$seoScore = $seoScore ?? 0;
$seoTone = ReportUi::scoreTone($seoScore);
?>
<div class="summary-band">
    <?php partial('partials/gauge', [
        'value' => Format::score($seoScore),
        'ratio' => $seoScore / 10,
        'tone' => $seoTone,
        'label' => 'Nota de SEO ' . Format::score($seoScore) . ' de 10',
    ]); ?>
    <div>
        <p class="summary-kicker">SEO</p>
        <p class="summary-title">
            <?php partial('partials/icon', ['tone' => $seoTone]); ?>
            <span><?= e(ReportUi::scoreLabel($seoScore)) ?></span>
        </p>
        <?php partial('partials/marks', ['marks' => ReportUi::seoMarks($data)]); ?>
    </div>
</div>
<p class="section-intro">Ausência de uma marcação não é tratada como prova de perda de ranking. O sinal positivo só aparece quando o item foi encontrado nesta coleta.</p>
<div class="check-list">
    <?php foreach ($data['checks'] as $check): ?>
        <article class="check check--<?= e($check['tone']) ?>">
            <h3><?= e($check['label']) ?></h3>
            <p><?= e($check['detail']) ?></p>
        </article>
    <?php endforeach; ?>
</div>

<h2>Title</h2>
<p><?= $data['title']['present'] ? e($data['title']['text']) : 'Ausente' ?> <span class="muted">(<?= e((string) $data['title']['length']) ?> caracteres)</span></p>

<h2>Meta description</h2>
<p><?= $data['description']['present'] ? e($data['description']['text']) : 'Ausente' ?> <span class="muted">(<?= e((string) $data['description']['length']) ?> caracteres)</span></p>

<h2>H1</h2>
<?php if ($data['h1']['items'] === []): ?>
    <p>Nenhum H1.</p>
<?php else: ?>
    <ul>
        <?php foreach ($data['h1']['items'] as $item): ?>
            <li><?= e($item !== '' ? $item : '(sem texto)') ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Headings</h2>
<?php if ($data['headings'] === []): ?>
    <p>Nenhum heading.</p>
<?php else: ?>
    <ol class="outline">
        <?php foreach ($data['headings'] as $heading): ?>
            <li class="outline-h<?= e((string) $heading['level']) ?>"><span>H<?= e((string) $heading['level']) ?></span> <?= e($heading['text'] !== '' ? $heading['text'] : '(sem texto)') ?></li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<h2>Canonical</h2>
<?php if (!$data['canonical']['present']): ?>
    <p>Não declarado.</p>
<?php else: ?>
    <p><?= e((string) $data['canonical']['url']) ?></p>
    <p class="muted"><?= ($data['canonical']['self'] ?? false) ? 'Auto-referente.' : 'Diferente da URL analisada.' ?> <?= e((string) $data['canonical']['note']) ?></p>
<?php endif; ?>

<h2>Robots e idioma</h2>
<dl class="facts">
    <div><dt>Meta robots</dt><dd><?= e($data['robots']['meta'] !== '' ? $data['robots']['meta'] : 'não declarada') ?></dd></div>
    <div><dt>X-Robots-Tag</dt><dd><?= e($data['robots']['header'] !== '' ? $data['robots']['header'] : 'não declarado') ?></dd></div>
    <div><dt>Lang</dt><dd><?= e($data['lang'] ?? 'ausente') ?></dd></div>
    <div><dt>Viewport</dt><dd><?= $report->mobile['viewport_present'] ? e((string) $report->mobile['viewport']) : 'ausente' ?></dd></div>
</dl>

<h2>Compartilhamento social</h2>
<h3 class="subhead">Open Graph</h3>
<dl class="facts">
    <?php foreach ($data['open_graph'] as $name => $value): ?>
        <div><dt><?= e($name) ?></dt><dd><?= e($value ?? 'não declarado') ?></dd></div>
    <?php endforeach; ?>
</dl>

<h3 class="subhead">X (Twitter)</h3>
<dl class="facts">
    <?php foreach ($data['twitter'] as $name => $value): ?>
        <div><dt><?= e($name) ?></dt><dd><?= e($value ?? 'não declarado') ?></dd></div>
    <?php endforeach; ?>
</dl>

<h2>Dados estruturados</h2>
<?php
$schemaEntities = $data['schema']['entities'] ?? [];
$schemaWithProperties = array_values(array_filter(
    $schemaEntities,
    static fn (array $entity): bool => ($entity['properties'] ?? []) !== []
));
?>
<?php if ($data['schema']['types'] === [] && $data['schema']['invalid'] === []): ?>
    <p>Nenhum JSON-LD válido foi encontrado.</p>
<?php endif; ?>
<?php if ($data['schema']['types'] !== []): ?>
    <h3 class="subhead">Tipos detectados</h3>
    <ul>
        <?php foreach ($data['schema']['types'] as $type): ?>
            <li><?= e($type) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($schemaWithProperties !== []): ?>
    <h3 class="subhead">Propriedades principais</h3>
    <?php foreach ($schemaWithProperties as $entity): ?>
        <p><strong><?= e($entity['type']) ?></strong></p>
        <dl class="facts">
            <?php foreach ($entity['properties'] as $property): ?>
                <div><dt><?= e($property['name']) ?></dt><dd><?= e($property['value']) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    <?php endforeach; ?>
<?php endif; ?>
<?php if ($data['schema']['invalid'] !== []): ?>
    <h3 class="subhead">Erros de leitura</h3>
    <?php foreach ($data['schema']['invalid'] as $invalid): ?>
        <p><?= e($invalid) ?></p>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Links</h2>
<dl class="facts">
    <div><dt>Internos</dt><dd><?= e((string) $data['links']['internal']) ?></dd></div>
    <div><dt>Externos</dt><dd><?= e((string) $data['links']['external']) ?></dd></div>
    <div><dt>nofollow</dt><dd><?= e((string) $data['links']['nofollow']) ?></dd></div>
    <div><dt>Âncoras vazias</dt><dd><?= e((string) $data['links']['empty']) ?></dd></div>
    <div><dt>Links HTTP</dt><dd><?= e((string) $data['links']['mixed_links']) ?></dd></div>
    <div><dt>Recursos HTTP</dt><dd><?= e((string) $data['links']['mixed_assets']) ?></dd></div>
</dl>

