<?php
/** @var list<array{label: string, tone: string}> $marks */
?>
<ul class="mark-row">
    <?php foreach ($marks as $mark): ?>
        <li class="mark-chip mark-chip--<?= e($mark['tone']) ?>">
            <?php partial('partials/icon', ['tone' => $mark['tone']]); ?>
            <span><?= e($mark['label']) ?></span>
        </li>
    <?php endforeach; ?>
</ul>
