<?php
use VitSeo\Support\ReportUi;

/** @var string $value */
/** @var float $ratio */
/** @var string $tone */
/** @var string $label */
$offset = ReportUi::ringOffset((float) $ratio);
?>
<div class="gauge gauge--<?= e($tone) ?>">
    <svg viewBox="0 0 100 100" role="img" aria-label="<?= e($label) ?>">
        <circle class="gauge-track" cx="50" cy="50" r="42"></circle>
        <circle class="gauge-value" cx="50" cy="50" r="42" stroke-dasharray="<?= e((string) ReportUi::RING) ?>" stroke-dashoffset="<?= e((string) $offset) ?>"></circle>
    </svg>
    <p class="gauge-readout"><?= e($value) ?></p>
</div>
