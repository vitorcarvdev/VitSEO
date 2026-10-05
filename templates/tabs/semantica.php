<?php
use VitSeo\Support\Format;
$data = $report->semantic;
$read = $data['readability'];
?>
<div class="callout"><p><?= e($data['note']) ?></p></div>
<div class="metric-grid">
    <article class="metric">
        <p class="metric-label">Palavras</p>
        <p class="metric-value"><?= e(Format::integer((int) $data['words'])) ?></p>
    </article>
    <article class="metric">
        <p class="metric-label">Parágrafos</p>
        <p class="metric-value"><?= e((string) $data['paragraphs']) ?></p>
    </article>
    <article class="metric">
        <p class="metric-label">Média por parágrafo</p>
        <p class="metric-value"><?= e(number_format((float) $data['avg_words'], 1, ',', '')) ?></p>
    </article>
    <article class="metric">
        <p class="metric-label">Legibilidade</p>
        <p class="metric-value"><?= $read['available'] ? e((string) $read['band']) : '—' ?></p>
    </article>
</div>
<dl class="facts">
    <div><dt>Média de caracteres por parágrafo</dt><dd><?= e(number_format((float) $data['avg_chars'], 0, ',', '')) ?></dd></div>
    <div><dt>Listas</dt><dd><?= e((string) $data['lists']) ?></dd></div>
    <div><dt>Tabelas</dt><dd><?= e((string) $data['tables']) ?></dd></div>
    <div><dt>Diversidade lexical</dt><dd><?= e(number_format((float) $data['ttr'], 2, ',', '')) ?></dd></div>
</dl>
<p class="muted">A diversidade é a razão entre termos distintos e ocorrências, depois das stopwords. Ela muda com o tamanho do texto e não é um índice oficial de qualidade.</p>
<h2>Headings</h2>
<ul class="inline-stats">
    <?php foreach ($data['headings'] as $level => $count): ?>
        <li>H<?= e((string) $level) ?> · <?= e((string) $count) ?></li>
    <?php endforeach; ?>
</ul>
<h2>Termos frequentes</h2>
<?php if ($data['terms'] === []): ?>
    <p class="empty">Sem termos repetidos suficientes.</p>
<?php else: ?>
    <ul class="chip-list">
        <?php foreach ($data['terms'] as $term): ?>
            <li><?= e($term['term']) ?> <span><?= e((string) $term['count']) ?></span></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<h2>Bigramas</h2>
<?php if ($data['bigrams'] === []): ?><p class="empty">Nenhum bigrama repetido pelo menos duas vezes.</p><?php else: ?>
    <ul class="chip-list"><?php foreach ($data['bigrams'] as $item): ?><li><?= e($item['text']) ?> <span><?= e((string) $item['count']) ?></span></li><?php endforeach; ?></ul>
<?php endif; ?>
<h2>Trigramas</h2>
<?php if ($data['trigrams'] === []): ?><p class="empty">Nenhum trigrama repetido pelo menos duas vezes.</p><?php else: ?>
    <ul class="chip-list"><?php foreach ($data['trigrams'] as $item): ?><li><?= e($item['text']) ?> <span><?= e((string) $item['count']) ?></span></li><?php endforeach; ?></ul>
<?php endif; ?>
<h2>Legibilidade aproximada</h2>
<?php if (!$read['available']): ?>
    <p><?= e($read['reason']) ?></p>
<?php else: ?>
    <p>Índice aproximado: <?= e(number_format((float) $read['score'], 1, ',', '')) ?> · <?= e((string) $read['band']) ?>.</p>
    <p>Palavras por frase: <?= e(number_format((float) $read['words_per_sentence'], 1, ',', '')) ?>. Sílabas por palavra: <?= e(number_format((float) $read['syllables_per_word'], 2, ',', '')) ?>.</p>
<?php endif; ?>
<p class="muted"><?= e($read['formula']) ?></p>
