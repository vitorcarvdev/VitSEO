<?php
$data = $report->mobile;
$mobileTone = match ($data['status']) {
    'Viewport ausente' => 'bad',
    'Risco potencial' => 'warn',
    default => 'ok',
};
$mobileLabel = match ($data['status']) {
    'Viewport ausente' => 'Problema',
    'Risco potencial' => 'Risco potencial',
    default => 'OK',
};
?>
<div class="summary-band">
    <div class="status-orb status-orb--<?= e($mobileTone) ?>" role="img" aria-label="<?= e($mobileLabel) ?>">
        <?php partial('partials/icon', ['tone' => $mobileTone]); ?>
    </div>
    <div>
        <p class="summary-kicker">Status</p>
        <p class="summary-title">
            <?php partial('partials/icon', ['tone' => $mobileTone]); ?>
            <span><?= e($mobileLabel) ?></span>
        </p>
        <p class="finding-copy"><?= e($data['summary']) ?></p>
    </div>
</div>
<?php if ($data['fixed_widths'] !== []): ?>
    <h2>Larguras fixas observadas</h2>
    <ul class="evidence-list">
        <?php foreach ($data['fixed_widths'] as $example): ?>
            <li><?= e($example) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<div class="callout">
    <p><?= e($data['note']) ?></p>
</div>
<dl class="facts">
    <div><dt>Viewport</dt><dd><?= $data['viewport_present'] ? e((string) $data['viewport']) : 'Ausente' ?></dd></div>
    <div><dt>Media queries</dt><dd><?= $data['media_queries']['found'] ? 'Encontradas' : 'Não encontradas no CSS lido' ?></dd></div>
    <div><dt>Tabelas</dt><dd><?= e((string) $data['tables']) ?></dd></div>
    <div><dt>Iframes</dt><dd><?= e((string) $data['iframes']) ?></dd></div>
    <div><dt>overflow-x no CSS lido</dt><dd><?= e((string) $data['overflow']) ?></dd></div>
    <div><dt>Indícios de max-width</dt><dd><?= e((string) $data['max_width_hints']) ?></dd></div>
    <div><dt>Menu móvel</dt><dd><?= $data['mobile_menu']['found'] ? 'Sinal identificado' : 'Não identificado' ?></dd></div>
</dl>
<?php if ($data['media_queries']['sources'] !== []): ?>
    <p>Onde apareceu <code>@media</code>:</p>
    <ul>
        <?php foreach ($data['media_queries']['sources'] as $source): ?>
            <li><?= e($source) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<p><?= e($data['mobile_menu']['evidence']) ?></p>
<?php foreach ($data['css_notes'] as $note): ?>
    <p class="muted"><?= e($note) ?></p>
<?php endforeach; ?>
