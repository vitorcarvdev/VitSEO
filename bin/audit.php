<?php

declare(strict_types=1);

use VitSeo\Analysis\AuditException;

require dirname(__DIR__) . '/bootstrap.php';

$url = $argv[1] ?? '';
if ($url === '') {
    fwrite(STDERR, "Uso: php bin/audit.php https://exemplo.com.br/\n");
    exit(1);
}

try {
    $report = vitseo_auditor()->audit($url);
} catch (AuditException $exception) {
    fwrite(STDERR, $exception->errorCode . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, "falha: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$lines = [
    'dominio: ' . $report->domain,
    'final: ' . $report->finalUrl,
    'http: ' . $report->httpStatus,
    'nota: ' . number_format($report->score, 1, '.', ''),
    'diagnostico: ' . $report->diagnosis,
    'h1: ' . ($report->seo['h1']['count']) . ' | ' . implode(' || ', $report->seo['h1']['items']),
    'canonical: ' . (($report->seo['canonical']['url'] ?? '') ?: 'ausente') . ' self=' . var_export($report->seo['canonical']['self'], true),
    'imagens: total=' . $report->images['total'] . ' alt=' . $report->images['com_alt'] . ' decorativas=' . $report->images['decorativas'] . ' revisar=' . $report->images['revisar'] . ' sem_alt=' . $report->images['sem_alt'],
    'sobre: ' . $report->eeat['pages']['about']['status'] . ' ' . (string) $report->eeat['pages']['about']['url'],
    'contato: ' . $report->eeat['pages']['contact']['status'] . ' ' . (string) $report->eeat['pages']['contact']['url'],
    'llms: ' . $report->geo['llms']['llms.txt']['label'] . ' / ' . $report->geo['llms']['llms-full.txt']['label'],
    'duracao_ms: ' . (int) $report->durationMs,
];

foreach ($report->findings as $finding) {
    if (str_contains(mb_strtolower($finding->title), 'llms') || $finding->severity === 'critico') {
        $lines[] = 'achado: [' . $finding->severity . '/' . $finding->classification . '] ' . $finding->title;
    }
}

echo implode(PHP_EOL, $lines) . PHP_EOL;
