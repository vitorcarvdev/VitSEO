<?php

declare(strict_types=1);

use VitSeo\Analysis\AuditException;
use VitSeo\Support\View;

require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$view = new View();
$error = null;
$urlValue = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $urlValue = trim((string) ($_POST['url'] ?? ''));
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'], $token)) {
        $error = [
            'code' => 'invalid_url',
            'title' => 'Solicitação expirada',
            'message' => 'Atualize a página e envie a URL novamente.',
        ];
    } elseif ($urlValue === '') {
        $error = [
            'code' => 'invalid_url',
            'title' => 'URL inválida',
            'message' => 'Informe uma URL http ou https.',
        ];
    } else {
        try {
            set_time_limit(120);
            $report = vitseo_auditor()->audit($urlValue);
            $view->render('report', [
                'title' => $report->domain,
                'report' => $report,
            ]);
            exit;
        } catch (AuditException $exception) {
            $error = vitseo_error_view($exception);
        } catch (Throwable $exception) {
            error_log('VitSEO: ' . $exception->getMessage());
            $error = [
                'code' => 'network',
                'title' => 'Falha na análise',
                'message' => 'Não foi possível concluir a análise desta URL.',
            ];
        }
    }
}

$view->render('home', [
    'title' => 'Analise um site',
    'error' => $error,
    'urlValue' => $urlValue,
    'csrf' => $_SESSION['csrf'],
]);
