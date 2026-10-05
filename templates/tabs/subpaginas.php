<?php $data = $report->subpages; ?>
<p class="section-intro"><?= e($data['limit_note']) ?> Links descobertos: <?= e((string) $data['discovered']) ?>. Verificados: <?= e((string) $data['checked']) ?>.</p>
<?php if ($data['items'] === []): ?>
    <p class="empty">Nenhum link interno identificado.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>URL</th><th>Âncora</th><th>Ocorrências</th><th>Status</th><th>Tipo</th></tr>
            </thead>
            <tbody>
                <?php foreach ($data['items'] as $item): ?>
                    <tr>
                        <td><?= e($item['url']) ?></td>
                        <td><?= e($item['anchor'] !== '' ? $item['anchor'] : '—') ?></td>
                        <td><?= e((string) $item['count']) ?></td>
                        <td><?= e((string) $item['status']) ?></td>
                        <td>Interno</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
