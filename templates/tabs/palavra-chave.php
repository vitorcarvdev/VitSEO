<?php
use VitSeo\Support\Format;
$data = $report->keywords;
?>
<div class="callout"><p><?= e($data['note']) ?></p></div>
<div class="metric-grid">
    <article class="metric">
        <p class="metric-label">Termos mais frequentes</p>
        <p class="metric-value"><?= e((string) count($data['terms'])) ?></p>
    </article>
    <article class="metric">
        <p class="metric-label">Tokens considerados</p>
        <p class="metric-value"><?= e((string) $data['total_tokens']) ?></p>
    </article>
</div>
<?php if ($data['terms'] !== []): ?>
    <h2>Termos mais frequentes no conteúdo analisado</h2>
    <ul class="chip-list">
        <?php foreach ($data['terms'] as $term): ?>
            <li><?= e($term['term']) ?> <span><?= e((string) $term['abs']) ?></span></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($data['terms'] === []): ?>
    <p class="empty">Não houve termos suficientes depois da limpeza do texto.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Termo</th>
                    <th>Frequência</th>
                    <th>Relativa</th>
                    <th>Title</th>
                    <th>H1</th>
                    <th>Headings</th>
                    <th>Corpo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['terms'] as $term): ?>
                    <tr>
                        <td><?= e($term['term']) ?></td>
                        <td><?= e((string) $term['abs']) ?></td>
                        <td><?= e(Format::percent((float) $term['rel'])) ?></td>
                        <td><?= $term['in_title'] ? 'sim' : 'não' ?></td>
                        <td><?= $term['in_h1'] ? 'sim' : 'não' ?></td>
                        <td><?= $term['in_headings'] ? 'sim' : 'não' ?></td>
                        <td><?= $term['in_body'] ? 'sim' : 'não' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
