<div class="section-intro">
    <p>Cada item traz a evidência coletada e a classificação. Recomendação não é o mesmo que erro comprovado. Itens não verificáveis ficam nas abas correspondentes.</p>
    <ul class="legend">
        <li><span class="pill pill--comprovado">Comprovado</span> evidência objetiva desta coleta</li>
        <li><span class="pill pill--recomendacao">Recomendação</span> boa prática ou oportunidade</li>
        <li><span class="pill pill--informativo">Informativo</span> dado útil, sem penalização</li>
        <li><span class="pill pill--risco_potencial">Risco potencial</span> indício, não confirmação visual</li>
    </ul>
</div>
<?php
$groups = [
    'critico' => ['Críticos', 'bad', 'Nenhum problema crítico', 'ok'],
    'importante' => ['Importantes', 'alert', 'Nenhum problema importante', 'ok'],
    'melhoria' => ['Melhorias', 'warn', 'Nenhuma melhoria registrada', 'info'],
    'informativo' => ['Informativos', 'info', 'Nenhum informativo neste grupo', 'info'],
];
foreach ($groups as $key => [$label, $tone, $empty, $emptyTone]):
    $items = $report->groups[$key];
?>
    <h2 class="group-title">
        <?= e($label) ?>
        <?php if ($items !== []): ?>
            <span class="count-badge count-badge--<?= e($tone) ?>"><?= e((string) count($items)) ?></span>
        <?php endif; ?>
    </h2>
    <?php if ($items === []): ?>
        <p class="empty-state empty-state--<?= e($emptyTone) ?>">
            <?php partial('partials/icon', ['tone' => $emptyTone]); ?>
            <span><?= e($empty) ?></span>
        </p>
    <?php else: ?>
        <?php foreach ($items as $finding): ?>
            <?php partial('partials/finding', ['finding' => $finding]); ?>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endforeach; ?>

<details class="score-details">
    <summary>Como a nota <?= e(\VitSeo\Support\Format::score($report->score)) ?> foi calculada</summary>
    <p>A nota parte de <?= e(\VitSeo\Support\Format::score((float) $report->scoreDetails['start'])) ?>. Problemas comprovados reduzem a nota. Recomendações e riscos potenciais têm teto de <?= e(number_format((float) $report->scoreDetails['recommendation_cap'], 1, ',', '')) ?> ponto. Informativos e itens não verificáveis não reduzem.</p>
    <?php if ($report->scoreDetails['lines'] === []): ?>
        <p>Nenhum desconto aplicado.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($report->scoreDetails['lines'] as $line): ?>
                <li><?= e($line['title']) ?> · −<?= e(number_format((float) $line['weight'], 2, ',', '')) ?> · <?= e(label_classification($line['classification'])) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p>Pesos completos em <code>config/scoring.php</code>.</p>
</details>
