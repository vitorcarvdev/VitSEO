<?php

declare(strict_types=1);

use VitSeo\Analysis\AuditException;
use VitSeo\Analysis\ScoreCalculator;
use VitSeo\Analysis\SiteAuditor;
use VitSeo\Http\CurlTransport;
use VitSeo\Http\SystemDnsResolver;
use VitSeo\Http\UrlFetcher;
use VitSeo\Http\UrlValidator;

define('VITSEO_ROOT', __DIR__);

require VITSEO_ROOT . '/src/autoload.php';

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');
ini_set('html_errors', '0');

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function partial(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    include VITSEO_ROOT . '/templates/' . $name . '.php';
}

function label_severity(string $severity): string
{
    return match ($severity) {
        'critico' => 'Crítico',
        'importante' => 'Importante',
        'melhoria' => 'Melhoria',
        default => 'Informativo',
    };
}

function label_classification(string $classification): string
{
    return match ($classification) {
        'comprovado' => 'Comprovado',
        'recomendacao' => 'Recomendação',
        'nao_verificavel' => 'Não verificável',
        'risco_potencial' => 'Risco potencial',
        default => 'Informativo',
    };
}

function label_status(string $status): string
{
    return match ($status) {
        'detectado', 'encontrado' => 'Encontrado',
        'nao_detectado', 'nao_encontrado' => 'Não encontrado',
        'nao_verificavel' => 'Não verificável',
        'inconclusivo' => 'Inconclusivo',
        default => $status,
    };
}

function vitseo_config(): array
{
    static $config;
    if ($config === null) {
        $config = require VITSEO_ROOT . '/config/app.php';
    }

    return $config;
}

function vitseo_auditor(): SiteAuditor
{
    static $auditor;
    if ($auditor instanceof SiteAuditor) {
        return $auditor;
    }

    $config = vitseo_config();
    $scoring = require VITSEO_ROOT . '/config/scoring.php';
    $fetcher = new UrlFetcher(
        new UrlValidator(new SystemDnsResolver()),
        new CurlTransport($config),
        $config,
    );

    return $auditor = new SiteAuditor($fetcher, new ScoreCalculator($scoring), $config);
}

/** @return array{code: string, title: string, message: string} */
function vitseo_error_view(AuditException $exception): array
{
    $titles = [
        'invalid_url' => 'URL inválida',
        'blocked' => 'Bloqueado por segurança',
        'dns' => 'Domínio inexistente',
        'timeout' => 'Tempo esgotado',
        'ssl' => 'SSL inválido',
        'empty' => 'HTML vazio',
        'unparseable' => 'HTML não interpretável',
        'too_large' => 'Resposta muito grande',
        'network' => 'Falha de conexão',
    ];

    return [
        'code' => $exception->errorCode,
        'title' => $titles[$exception->errorCode] ?? 'Falha na análise',
        'message' => $exception->getMessage(),
    ];
}
