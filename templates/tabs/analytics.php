<?php
$data = $report->analytics;
$found = $data['tags'] !== [];
$tone = $found ? 'ok' : 'info';
?>
<div class="summary-band">
    <div class="status-orb status-orb--<?= e($tone) ?>" role="img" aria-label="<?= $found ? 'Tag detectada' : 'Nenhuma tag detectada' ?>">
        <?php partial('partials/icon', ['tone' => $tone]); ?>
    </div>
    <div>
        <p class="summary-kicker">Status</p>
        <p class="summary-title">
            <?php partial('partials/icon', ['tone' => $tone]); ?>
            <span><?= $found ? 'Tag detectada' : 'Nenhuma tag detectada' ?></span>
        </p>
        <p class="finding-copy"><?= $found ? 'Presença da tag detectada. O VitSEO não confirma se os eventos estão sendo enviados corretamente.' : 'Nenhuma tag conhecida apareceu no HTML. Isso não afirma que a medição esteja ausente fora desta página.' ?></p>
    </div>
</div>
<div class="callout"><p><?= e($data['note']) ?></p></div>
<?php if ($data['tags'] === []): ?>
    <p>Nenhuma tag conhecida foi detectada no HTML.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Ferramenta</th><th>ID</th><th>Fonte</th></tr>
            </thead>
            <tbody>
                <?php foreach ($data['tags'] as $tag): ?>
                    <tr>
                        <td><?= e($tag['name']) ?></td>
                        <td><?= e($tag['id'] ?? 'não extraído') ?></td>
                        <td><?= e($tag['source']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
