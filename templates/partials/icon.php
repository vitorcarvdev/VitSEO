<?php
/** @var string $tone */
$tone = $tone ?? 'info';
?>
<svg class="state-icon state-icon--<?= e($tone) ?>" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <?php if ($tone === 'bad'): ?>
        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"></circle>
        <path d="M9 9l6 6M15 9l-6 6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"></path>
    <?php elseif ($tone === 'info'): ?>
        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"></circle>
        <path d="M12 11v5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"></path>
        <circle cx="12" cy="8" r="0.9" fill="currentColor"></circle>
    <?php elseif ($tone === 'warn' || $tone === 'alert'): ?>
        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"></circle>
        <path d="M12 8v5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"></path>
        <circle cx="12" cy="16.2" r="0.9" fill="currentColor"></circle>
    <?php else: ?>
        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.7"></circle>
        <path d="M7.8 12.2l2.6 2.6 5.8-5.8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"></path>
    <?php endif; ?>
</svg>
