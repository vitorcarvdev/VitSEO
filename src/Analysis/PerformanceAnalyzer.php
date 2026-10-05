<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Http\FetchResult;
use VitSeo\Support\Format;
use VitSeo\Support\HtmlParser;

final class PerformanceAnalyzer
{
    /**
     * Mede apenas a requisição HTTP desta coleta.
     * Não representa Core Web Vitals, LCP nem o carregamento no navegador.
     *
     * @return array{findings: list<Finding>, data: array<string, mixed>}
     */
    public function analyze(HtmlParser $page, FetchResult $fetch): array
    {
        $findings = [];
        $status = $fetch->status;
        if ($status >= 500 || in_array($status, [404, 410], true)) {
            $findings[] = new Finding(
                'Status HTTP ' . $status,
                'critico',
                'comprovado',
                'A resposta final retornou HTTP ' . $status . '.',
                'A URL analisada não retornou 200. O relatório descreve a resposta recebida.',
                null,
                'velocidade'
            );
        } elseif ($status !== 200) {
            $explanation = in_array($status, [401, 403], true)
                ? 'O servidor recusou ou restringiu a resposta. Isso pode valer só para este pedido, não para todos os visitantes.'
                : 'A resposta final não é HTTP 200.';
            $findings[] = new Finding(
                'Status HTTP ' . $status,
                'importante',
                'comprovado',
                'A resposta final retornou HTTP ' . $status . '.',
                $explanation,
                null,
                'velocidade'
            );
        }

        $htmlBytes = strlen($fetch->body);
        $encoding = strtolower($fetch->header('content-encoding'));
        $compressed = $encoding !== '' && preg_match('/gzip|br|deflate|zstd/', $encoding) === 1;
        if (!$compressed && $htmlBytes > 150000) {
            $findings[] = new Finding(
                'Compressão não confirmada',
                'melhoria',
                'recomendacao',
                'Content-Encoding ausente. HTML decodificado com ' . Format::bytes($htmlBytes) . '.',
                'O header Content-Encoding não informou gzip, br, deflate ou zstd. A compressão pode existir sem aparecer neste header, então isto não é uma prova de página sem compressão.',
                null,
                'velocidade'
            );
        }
        if ($htmlBytes > 1500000) {
            $findings[] = new Finding(
                'HTML extenso',
                'melhoria',
                'comprovado',
                'O HTML decodificado tem ' . Format::bytes($htmlBytes) . '.',
                'O documento é grande. Isso aumenta o tempo de transferência, sem representar sozinho a velocidade percebida.',
                null,
                'velocidade'
            );
        }

        $css = count($page->stylesheetHrefs());
        $js = count($page->scriptSrcs());
        $images = count($page->images());
        $total = (float) ($fetch->timings['total'] ?? 0);
        $infra = $this->infra($fetch->headers);
        $summary = 'Requisição HTTP em ' . Format::seconds($total) . ' · HTML ' . Format::bytes($htmlBytes);
        if ($encoding !== '') {
            $summary .= ' · ' . $encoding;
        }

        return [
            'findings' => $findings,
            'data' => [
                'status' => $status,
                'dns_seconds' => $fetch->dnsSeconds,
                'timings' => $fetch->timings,
                'bytes_downloaded' => $fetch->bytesDownloaded,
                'html_bytes' => $htmlBytes,
                'content_encoding' => $encoding !== '' ? $encoding : null,
                'compressed' => $compressed,
                'content_type' => $fetch->header('content-type') !== '' ? $fetch->header('content-type') : null,
                'infra' => $infra,
                'resources' => ['css' => $css, 'js' => $js, 'images' => $images],
                'summary' => $summary,
                'figure' => Format::seconds($total),
                'redirects' => $fetch->redirects,
                'note' => 'Estes tempos vêm da requisição cURL feita pelo VitSEO. Não são Core Web Vitals, LCP nem o carregamento completo no navegador.',
            ],
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function infra(array $headers): array
    {
        $notes = [];
        $server = $headers['server'] ?? '';
        $serverLower = strtolower($server);
        if (isset($headers['cf-ray']) || str_contains($serverLower, 'cloudflare')) {
            $notes[] = 'Cloudflare, pelo header cf-ray ou Server.';
        }
        if (isset($headers['x-amz-cf-id']) || str_contains($serverLower, 'cloudfront')) {
            $notes[] = 'Amazon CloudFront, por header específico.';
        }
        if (isset($headers['x-vercel-id']) || str_contains($serverLower, 'vercel')) {
            $notes[] = 'Vercel, por header específico.';
        }
        if (str_contains($serverLower, 'fastly') || isset($headers['x-fastly-request-id'])) {
            $notes[] = 'Fastly, por header específico.';
        }
        if (isset($headers['x-akamai-transformed']) || str_contains(strtolower($headers['server-timing'] ?? ''), 'akamai')) {
            $notes[] = 'Akamai, por header específico.';
        }
        if ($server !== '') {
            $notes[] = 'Header Server: ' . $server;
        }
        if ($notes === []) {
            $notes[] = 'Nenhum header permitiu identificar servidor ou CDN.';
        }

        return $notes;
    }
}
