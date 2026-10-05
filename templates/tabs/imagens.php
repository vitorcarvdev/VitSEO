<?php
use VitSeo\Support\Format;
use VitSeo\Support\ReportUi;

$data = $report->images;
$imageScore = null;
foreach ($report->categories as $card) {
    if ($card['label'] === 'Imagens') {
        $imageScore = $card['score'];
    }
}
$proven = (int) $data['total'] > 0 && (int) $data['sem_alt'] === 0;
$imageTitle = (int) $data['total'] === 0
    ? 'Nenhuma imagem encontrada'
    : ($proven ? 'Nenhum problema comprovado' : (string) $data['sem_alt'] . ' sem alt');
$imageTone = (int) $data['total'] === 0 ? 'info' : ($proven ? 'ok' : 'bad');
?>
<div class="summary-band">
    <?php if ($imageScore === null): ?>
        <div class="status-orb status-orb--info" role="img" aria-label="Imagens não avaliadas">
            <?php partial('partials/icon', ['tone' => 'info']); ?>
        </div>
    <?php else: ?>
        <?php partial('partials/gauge', [
            'value' => Format::score((float) $imageScore),
            'ratio' => ((float) $imageScore) / 10,
            'tone' => ReportUi::scoreTone((float) $imageScore),
            'label' => 'Nota de imagens ' . Format::score((float) $imageScore) . ' de 10',
        ]); ?>
    <?php endif; ?>
    <div>
        <p class="summary-kicker">Imagens</p>
        <p class="summary-title">
            <?php partial('partials/icon', ['tone' => $imageTone]); ?>
            <span><?= e($imageTitle) ?></span>
        </p>
        <ul class="mark-row">
            <li class="mark-chip mark-chip--info"><span><?= e((string) $data['total']) ?> imagens</span></li>
            <li class="mark-chip mark-chip--ok"><span><?= e((string) $data['com_alt']) ?> com alt</span></li>
            <li class="mark-chip mark-chip--info"><span><?= e((string) $data['decorativas']) ?> decorativas</span></li>
            <li class="mark-chip mark-chip--<?= (int) $data['sem_alt'] > 0 ? 'bad' : 'ok' ?>"><span><?= e((string) $data['sem_alt']) ?> sem alt</span></li>
        </ul>
    </div>
</div>
<p class="section-intro"><?= e($data['size_note']) ?></p>
<dl class="facts">
    <div><dt>Total</dt><dd><?= e((string) $data['total']) ?></dd></div>
    <div><dt>Com alt</dt><dd><?= e((string) $data['com_alt']) ?></dd></div>
    <div><dt>Decorativas</dt><dd><?= e((string) $data['decorativas']) ?></dd></div>
    <div><dt>Revisar alt vazio</dt><dd><?= e((string) $data['revisar']) ?></dd></div>
    <div><dt>Sem alt</dt><dd><?= e((string) $data['sem_alt']) ?></dd></div>
    <div><dt>Pixels 1×1</dt><dd><?= e((string) $data['pixels']) ?></dd></div>
    <div><dt>Sem dimensões</dt><dd><?= e((string) $data['sem_dimensoes']) ?></dd></div>
    <div><dt>Lazy</dt><dd><?= e((string) $data['lazy']) ?></dd></div>
</dl>
<?php if ($data['formats'] !== []): ?>
    <p>Formatos: <?php $bits = []; foreach ($data['formats'] as $format => $count) { $bits[] = $format . ' ' . $count; } echo e(implode(' · ', $bits)); ?></p>
<?php endif; ?>
<?php if ($data['items'] === []): ?>
    <p class="empty">Nenhuma imagem <code>&lt;img&gt;</code> encontrada.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Src</th>
                    <th>Alt</th>
                    <th>Texto</th>
                    <th>W</th>
                    <th>H</th>
                    <th>Loading</th>
                    <th>Decoding</th>
                    <th>Formato</th>
                    <th>Domínio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['items'] as $item): ?>
                    <tr>
                        <td><?= e(\VitSeo\Support\Text::excerpt($item['src'], 80)) ?></td>
                        <td><?= e($item['alt_label']) ?></td>
                        <td><?= e($item['alt'] === null ? '—' : ($item['alt'] === '' ? '(vazio)' : \VitSeo\Support\Text::excerpt($item['alt'], 80))) ?></td>
                        <td><?= e($item['width'] !== '' ? $item['width'] : '—') ?></td>
                        <td><?= e($item['height'] !== '' ? $item['height'] : '—') ?></td>
                        <td><?= e($item['loading'] !== '' ? $item['loading'] : '—') ?></td>
                        <td><?= e($item['decoding'] !== '' ? $item['decoding'] : '—') ?></td>
                        <td><?= e($item['format']) ?></td>
                        <td><?= e($item['host']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($data['truncated']): ?><p class="muted">A tabela mostra as primeiras imagens. Os totais incluem todas.</p><?php endif; ?>
<?php endif; ?>
