<?php
use VitSeo\Support\Format;
use VitSeo\Support\ReportUi;

$data = $report->performance;
$timings = $data['timings'];
$seconds = (float) ($timings['total'] ?? 0);
$pace = ReportUi::httpPace($seconds);
$encoding = is_string($data['content_encoding']) && $data['content_encoding'] !== '' ? $data['content_encoding'] : 'não detectada';
$httpTone = $data['status'] === 200 ? 'ok' : ($data['status'] >= 500 || in_array($data['status'], [404, 410], true) ? 'bad' : 'alert');
?>
<div class="summary-band">
    <?php partial('partials/gauge', [
        'value' => ReportUi::compactSeconds($seconds),
        'ratio' => $pace['ratio'],
        'tone' => $pace['tone'],
        'label' => 'Tempo da requisição HTTP ' . ReportUi::compactSeconds($seconds) . ', ' . $pace['label'],
    ]); ?>
    <div>
        <p class="summary-kicker">Classificação HTTP</p>
        <p class="summary-title">
            <?php partial('partials/icon', ['tone' => $pace['tone']]); ?>
            <span><?= e($pace['label']) ?></span>
        </p>
        <p class="muted">O arco acompanha o tempo medido. O valor no centro é esse tempo, não uma nota de PageSpeed.</p>
    </div>
</div>

<div class="metric-grid">
    <article class="metric">
        <p class="metric-label">Tempo da requisição</p>
        <p class="metric-value"><?= e(ReportUi::compactSeconds($seconds)) ?></p>
    </article>
    <article class="metric">
        <p class="metric-label">TTFB aproximado</p>
        <p class="metric-value"><?= e(Format::seconds((float) ($timings['starttransfer'] ?? 0))) ?></p>
    </article>
    <article class="metric">
        <p class="metric-label">Tamanho do HTML</p>
        <p class="metric-value"><?= e(Format::bytes((int) $data['html_bytes'])) ?></p>
    </article>
    <article class="metric metric--<?= e($httpTone) ?>">
        <p class="metric-label">Status HTTP</p>
        <p class="metric-value"><?= e((string) $data['status']) ?></p>
    </article>
    <article class="metric">
        <p class="metric-label">Compressão</p>
        <p class="metric-value"><?= e($encoding) ?></p>
    </article>
    <article class="metric">
        <p class="metric-label">Servidor / CDN</p>
        <p class="metric-value"><?= e(ReportUi::infraLabel($data['infra'])) ?></p>
    </article>
    <article class="metric metric--<?= e($pace['tone']) ?>">
        <p class="metric-label">Classificação HTTP</p>
        <p class="metric-value"><?= e($pace['label']) ?></p>
    </article>
</div>

<div class="callout">
    <p>Esta medição representa a requisição HTTP realizada pelo servidor do VitSEO. Não substitui PageSpeed/Lighthouse nem Core Web Vitals.</p>
</div>

<h2>Faixas do arco</h2>
<dl class="facts">
    <div><dt>Até 1,5 s</dt><dd>Excelente</dd></div>
    <div><dt>Acima de 1,5 s até 2,5 s</dt><dd>Bom</dd></div>
    <div><dt>Acima de 2,5 s até 4,5 s</dt><dd>Regular</dd></div>
    <div><dt>Acima de 4,5 s</dt><dd>Lento</dd></div>
</dl>
<p class="muted">O tempo até o primeiro byte é o <code>starttransfer_time</code> do cURL nesta máquina, não o TTFB medido no navegador do visitante.</p>

<h2>Detalhes da requisição</h2>
<dl class="facts">
    <div><dt>Resolução DNS</dt><dd><?= e(Format::seconds((float) $data['dns_seconds'])) ?></dd></div>
    <div><dt>Conexão TCP</dt><dd><?= e(Format::seconds((float) ($timings['connect'] ?? 0))) ?></dd></div>
    <div><dt>TLS</dt><dd><?= e(Format::seconds(max(0, (float) ($timings['appconnect'] ?? 0) - (float) ($timings['connect'] ?? 0)))) ?></dd></div>
    <div><dt>Bytes transferidos</dt><dd><?= e(Format::bytes((int) $data['bytes_downloaded'])) ?></dd></div>
    <div><dt>HTML decodificado</dt><dd><?= e(Format::bytes((int) $data['html_bytes'])) ?></dd></div>
    <div><dt>Content-Type</dt><dd><?= e($data['content_type'] ?? 'não informado') ?></dd></div>
    <div><dt>CSS no HTML</dt><dd><?= e((string) $data['resources']['css']) ?></dd></div>
    <div><dt>JS no HTML</dt><dd><?= e((string) $data['resources']['js']) ?></dd></div>
    <div><dt>Imagens no HTML</dt><dd><?= e((string) $data['resources']['images']) ?></dd></div>
</dl>
<h2>Servidor e CDN</h2>
<p class="muted">Inferência apenas pelos headers recebidos.</p>
<ul>
    <?php foreach ($data['infra'] as $note): ?>
        <li><?= e($note) ?></li>
    <?php endforeach; ?>
</ul>
<?php if ($data['redirects'] !== []): ?>
    <h2>Redirecionamentos seguidos</h2>
    <ol>
        <?php foreach ($data['redirects'] as $url): ?>
            <li><?= e($url) ?></li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
<p class="muted">Uma integração futura com PageSpeed ou Lighthouse pode entrar nesta aba sem ser misturada a estes tempos.</p>
