<?php
use VitSeo\Support\ReportUi;

$data = $report->eeat;
$signals = 0;
foreach (ReportUi::eeatMarks($data) as $mark) {
    if ($mark['tone'] === 'ok') {
        $signals++;
    }
}
$eeatTone = $signals > 0 ? 'ok' : 'info';
?>
<div class="summary-band">
    <div class="status-orb status-orb--<?= e($eeatTone) ?>" role="img" aria-label="<?= $signals > 0 ? 'Sinais institucionais encontrados' : 'Nenhum sinal objetivo detectado' ?>">
        <?php partial('partials/icon', ['tone' => $eeatTone]); ?>
    </div>
    <div>
        <p class="summary-kicker">Status</p>
        <p class="summary-title">
            <?php partial('partials/icon', ['tone' => $eeatTone]); ?>
            <span><?= $signals > 0 ? 'Sinais institucionais encontrados' : 'Nenhum sinal objetivo detectado' ?></span>
        </p>
        <?php partial('partials/marks', ['marks' => ReportUi::eeatMarks($data)]); ?>
    </div>
</div>
<div class="callout">
    <p><?= e($data['note']) ?></p>
</div>
<?php foreach ($data['pages'] as $page): ?>
    <article class="block">
        <h2><?= e($page['label']) ?></h2>
        <p class="pill pill--<?= e($page['status'] === 'detectado' ? 'comprovado' : ($page['status'] === 'nao_verificavel' ? 'nao_verificavel' : 'informativo')) ?>"><?= e(label_status($page['status'])) ?></p>
        <?php if ($page['url']): ?><p><?= e($page['url']) ?></p><?php endif; ?>
        <p class="muted"><?= e($page['evidence']) ?></p>
    </article>
<?php endforeach; ?>

<h2>Autoria</h2>
<p><?= e(label_status($data['author']['status'])) ?>. <?= e($data['author']['evidence']) ?></p>

<h2>Schema de identidade</h2>
<p><?= $data['schemas'] !== [] ? e(implode(', ', $data['schemas'])) : 'Organization, LocalBusiness ou Person não detectados.' ?></p>

<h2>Contato publicado</h2>
<dl class="facts">
    <div><dt>Telefone</dt><dd><?= $data['phones'] !== [] ? e(implode(' · ', $data['phones'])) : 'Não detectado' ?></dd></div>
    <div><dt>E-mail</dt><dd><?= $data['emails'] !== [] ? e(implode(' · ', $data['emails'])) : 'Não detectado' ?></dd></div>
    <div><dt>Endereço</dt><dd><?= $data['addresses'] !== [] ? e(implode(' · ', $data['addresses'])) : 'Não detectado' ?></dd></div>
    <div><dt>CNPJ</dt><dd><?= $data['cnpj']['detected'] ? e(implode(' · ', $data['cnpj']['values'])) : 'Não detectado' ?></dd></div>
</dl>
<p class="muted">CNPJ só entra quando o formato pontuado aparece no texto. A ausência não é tratada como requisito de ranking.</p>

<h2>Depoimentos e portfólio</h2>
<p>Depoimentos: <?= e(label_status($data['testimonials']['status'])) ?>. <?= e($data['testimonials']['evidence']) ?></p>
<p>Cases ou portfólio: <?= e(label_status($data['portfolio']['status'])) ?>. <?= e($data['portfolio']['evidence']) ?></p>

<h2>WhatsApp</h2>
<?php if ($data['whatsapp'] === []): ?>
    <p>Nenhum link de WhatsApp identificado. Link de WhatsApp não é classificado como página Sobre.</p>
<?php else: ?>
    <ul>
        <?php foreach ($data['whatsapp'] as $url): ?>
            <li><?= e($url) ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="muted">Estes links não foram usados como página Sobre ou de contato.</p>
<?php endif; ?>

<h2>Links externos</h2>
<?php if ($data['external'] === []): ?>
    <p>Nenhum link externo foi listado.</p>
<?php else: ?>
    <ul>
        <?php foreach ($data['external'] as $url): ?>
            <li><?= e($url) ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="muted">Presença de link externo não significa citação ou autoridade.</p>
<?php endif; ?>
