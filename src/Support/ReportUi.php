<?php

declare(strict_types=1);

namespace VitSeo\Support;

use VitSeo\Analysis\AuditReport;
use VitSeo\Analysis\Finding;

/**
 * Estado visual do relatório. Não recalcula a nota e não altera achados.
 *
 * Faixas da nota já calculada, só para cor do arco de SEO e Imagens:
 * 8,0–10,0 verde; 6,0–7,9 amarelo; 0–5,9 vermelho.
 *
 * Faixas do arco de velocidade, pelo tempo total da requisição HTTP:
 * até 1,5 s Excelente; acima de 1,5 até 2,5 s Bom;
 * acima de 2,5 até 4,5 s Regular; acima de 4,5 s Lento.
 * O centro do arco mostra o tempo medido, não uma nota.
 */
final class ReportUi
{
    public const RING = 263.89;

    public static function scoreTone(float $score): string
    {
        if ($score >= 8) {
            return 'ok';
        }
        if ($score >= 6) {
            return 'warn';
        }

        return 'bad';
    }

    public static function scoreLabel(float $score): string
    {
        return match (self::scoreTone($score)) {
            'ok' => 'Saudável',
            'warn' => 'Atenção',
            default => 'Problema',
        };
    }

    public static function ringOffset(float $ratio): float
    {
        $ratio = max(0, min(1, $ratio));

        return self::RING * (1 - $ratio);
    }

    /**
     * @return array{tone: string, label: string, ratio: float}
     */
    public static function httpPace(float $seconds): array
    {
        $seconds = max(0, $seconds);
        if ($seconds <= 1.5) {
            $tone = 'ok';
            $label = 'Excelente';
            $visual = 10 - ($seconds / 1.5) * 2;
        } elseif ($seconds <= 2.5) {
            $tone = 'good';
            $label = 'Bom';
            $visual = 8 - (($seconds - 1.5) / 1) * 1.5;
        } elseif ($seconds <= 4.5) {
            $tone = 'warn';
            $label = 'Regular';
            $visual = 6.5 - (($seconds - 2.5) / 2) * 2.5;
        } else {
            $tone = 'bad';
            $label = 'Lento';
            $visual = max(0, 4 - min(4, (($seconds - 4.5) / 5.5) * 4));
        }

        return [
            'tone' => $tone,
            'label' => $label,
            'ratio' => max(0, min(1, $visual / 10)),
        ];
    }

    public static function compactSeconds(float $seconds): string
    {
        if ($seconds < 0.01) {
            return (string) max(0, (int) round($seconds * 1000)) . 'ms';
        }
        if ($seconds < 10) {
            return number_format($seconds, 2, ',', '') . 's';
        }

        return number_format($seconds, 1, ',', '') . 's';
    }

    /**
     * @return array{critical: int, important: int}
     */
    public static function provenCounts(AuditReport $report): array
    {
        $critical = 0;
        $important = 0;
        foreach ($report->findings as $finding) {
            if ($finding->classification !== 'comprovado') {
                continue;
            }
            if ($finding->severity === 'critico') {
                $critical++;
            } elseif ($finding->severity === 'importante') {
                $important++;
            }
        }

        return ['critical' => $critical, 'important' => $important];
    }

    /**
     * @return array{tone: string, title: string, detail: string}
     */
    public static function verdict(int $critical, int $important): array
    {
        if ($critical > 0) {
            return [
                'tone' => 'bad',
                'title' => self::plural($critical, 'problema crítico encontrado', 'problemas críticos encontrados'),
                'detail' => $important > 0
                    ? self::plural($important, 'problema importante encontrado', 'problemas importantes encontrados')
                    : '',
            ];
        }
        if ($important > 0) {
            return [
                'tone' => 'warn',
                'title' => self::plural($important, 'problema importante encontrado', 'problemas importantes encontrados'),
                'detail' => '',
            ];
        }

        return [
            'tone' => 'ok',
            'title' => 'Nenhum problema crítico ou importante comprovado',
            'detail' => '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function categoryCards(AuditReport $report): array
    {
        $scores = [];
        foreach ($report->categories as $card) {
            $scores[(string) $card['label']] = $card['score'];
        }
        $seconds = (float) ($report->performance['timings']['total'] ?? 0);
        $pace = self::httpPace($seconds);
        $seoScore = (float) ($scores['SEO'] ?? 0);
        $imageScore = $scores['Imagens'] ?? null;

        return [
            self::paceCard($pace, $seconds),
            self::imageCard($report, $imageScore),
            self::mobileCard($report->mobile),
            self::scoreCard('SEO', $seoScore, self::seoExtra($report)),
            self::geoCard($report->geo),
            self::eeatCard($report->eeat),
            self::analyticsCard($report->analytics),
        ];
    }

    /**
     * @return array<string, array{tone: string, label: string}>
     */
    public static function tabs(AuditReport $report): array
    {
        $cards = [];
        foreach (self::categoryCards($report) as $card) {
            $cards[(string) $card['key']] = $card;
        }

        return [
            'problemas' => self::problemTab($report),
            'velocidade' => ['tone' => (string) $cards['velocidade']['tone'], 'label' => (string) $cards['velocidade']['status']],
            'seo' => ['tone' => (string) $cards['seo']['tone'], 'label' => (string) $cards['seo']['status']],
            'geo' => ['tone' => 'info', 'label' => 'Informativo'],
            'eeat' => ['tone' => (string) $cards['eeat']['tone'], 'label' => (string) $cards['eeat']['status']],
            'imagens' => ['tone' => (string) $cards['imagens']['tone'], 'label' => (string) $cards['imagens']['status']],
            'mobile' => ['tone' => (string) $cards['mobile']['tone'], 'label' => (string) $cards['mobile']['status']],
            'analytics' => ['tone' => (string) $cards['analytics']['tone'], 'label' => (string) $cards['analytics']['status']],
            'subpaginas' => self::subpageTab($report->subpages),
            'palavra-chave' => ['tone' => 'info', 'label' => 'Informativo'],
            'semantica' => ['tone' => 'info', 'label' => 'Informativo'],
        ];
    }

    /**
     * @param array<string, mixed> $seo
     * @return list<array{label: string, tone: string}>
     */
    public static function seoMarks(array $seo): array
    {
        $h1 = (int) ($seo['h1']['count'] ?? 0);
        $canonical = $seo['canonical'] ?? [];
        $schema = $seo['schema'] ?? [];
        if (($schema['invalid'] ?? []) !== []) {
            $schemaTone = 'bad';
        } elseif (($schema['types'] ?? []) !== []) {
            $schemaTone = 'ok';
        } else {
            $schemaTone = 'info';
        }

        if (empty($canonical['present'])) {
            $canonicalTone = 'warn';
        } elseif (!empty($canonical['self'])) {
            $canonicalTone = 'ok';
        } else {
            $canonicalTone = 'info';
        }

        return [
            ['label' => 'Title', 'tone' => !empty($seo['title']['present']) ? 'ok' : 'bad'],
            ['label' => 'Description', 'tone' => !empty($seo['description']['present']) ? 'ok' : 'warn'],
            ['label' => 'H1', 'tone' => $h1 === 1 ? 'ok' : 'warn'],
            ['label' => 'Canonical', 'tone' => $canonicalTone],
            ['label' => 'Indexação', 'tone' => !empty($seo['robots']['noindex']) ? 'bad' : 'ok'],
            ['label' => 'Schema', 'tone' => $schemaTone],
        ];
    }

    /**
     * @param array<string, mixed> $geo
     * @return list<array{label: string, tone: string}>
     */
    public static function geoMarks(array $geo): array
    {
        $robotsFound = !empty($geo['robots']['found']);
        $sitemapFound = false;
        foreach ($geo['sitemaps'] ?? [] as $sitemap) {
            if (!empty($sitemap['found'])) {
                $sitemapFound = true;
                break;
            }
        }
        $marks = [
            ['label' => $robotsFound ? 'robots.txt acessível' : 'robots.txt não encontrado', 'tone' => $robotsFound ? 'ok' : 'info'],
            ['label' => $sitemapFound ? 'sitemap detectado' : 'sitemap não localizado', 'tone' => $sitemapFound ? 'ok' : 'info'],
            ['label' => ($geo['schema_types'] ?? []) !== [] ? 'schema detectado' : 'schema não detectado', 'tone' => ($geo['schema_types'] ?? []) !== [] ? 'ok' : 'info'],
        ];
        foreach ($geo['llms'] ?? [] as $name => $item) {
            $found = ($item['state'] ?? '') === 'encontrado';
            $marks[] = [
                'label' => $found ? $name . ' encontrado' : $name . ' não encontrado',
                'tone' => 'info',
            ];
        }

        return $marks;
    }

    /**
     * @param array<string, mixed> $eeat
     * @return list<array{label: string, tone: string}>
     */
    public static function eeatMarks(array $eeat): array
    {
        $pages = $eeat['pages'] ?? [];
        $marks = [];
        foreach ([
            'contact' => ['Contato', 'não encontrado', 'não verificável'],
            'privacy' => ['Privacidade', 'não encontrada', 'não verificável'],
            'about' => ['Sobre', 'não encontrada', 'não verificável'],
            'terms' => ['Termos', 'não encontrados', 'não verificáveis'],
        ] as $key => [$label, $missing, $uncertain]) {
            $status = (string) ($pages[$key]['status'] ?? 'nao_detectado');
            if ($status === 'detectado') {
                $marks[] = ['label' => $label, 'tone' => 'ok'];
                continue;
            }
            $marks[] = ['label' => $label . ' ' . ($status === 'nao_verificavel' ? $uncertain : $missing), 'tone' => 'info'];
        }
        $marks[] = self::presenceMark('Telefone', ($eeat['phones'] ?? []) !== []);
        $marks[] = self::presenceMark('E-mail', ($eeat['emails'] ?? []) !== []);
        $marks[] = [
            'label' => !empty($eeat['cnpj']['detected']) ? 'CNPJ publicado' : 'CNPJ não detectado',
            'tone' => !empty($eeat['cnpj']['detected']) ? 'ok' : 'info',
        ];

        return $marks;
    }

    /** @param list<string> $notes */
    public static function infraLabel(array $notes): string
    {
        $blob = implode(' ', $notes);
        foreach (['Cloudflare', 'CloudFront', 'Vercel', 'Fastly', 'Akamai'] as $name) {
            if (str_contains($blob, $name)) {
                return $name;
            }
        }
        foreach ($notes as $note) {
            if (str_starts_with($note, 'Header Server: ')) {
                return trim(substr($note, strlen('Header Server: ')));
            }
        }

        return 'Não identificado';
    }

    public static function toneText(string $tone): string
    {
        return match ($tone) {
            'ok' => 'correto',
            'good' => 'bom',
            'warn' => 'atenção',
            'alert' => 'importante',
            'bad' => 'problema',
            default => 'informativo',
        };
    }

    /**
     * @return array{tone: string, label: string}
     */
    private static function problemTab(AuditReport $report): array
    {
        if ($report->groups['critico'] !== []) {
            return ['tone' => 'bad', 'label' => 'Problema crítico'];
        }
        if ($report->groups['importante'] !== []) {
            return ['tone' => 'alert', 'label' => 'Problema importante'];
        }
        if ($report->groups['melhoria'] !== []) {
            return ['tone' => 'warn', 'label' => 'Melhoria'];
        }
        if ($report->groups['informativo'] !== []) {
            return ['tone' => 'info', 'label' => 'Informativo'];
        }

        return ['tone' => 'ok', 'label' => 'Sem problemas'];
    }

    /**
     * @param array<string, mixed> $subpages
     * @return array{tone: string, label: string}
     */
    private static function subpageTab(array $subpages): array
    {
        $warn = false;
        $bad = false;
        foreach ($subpages['items'] ?? [] as $item) {
            if (!is_numeric($item['status'] ?? null)) {
                continue;
            }
            $status = (int) $item['status'];
            if ($status >= 500) {
                $bad = true;
            } elseif ($status >= 400) {
                $warn = true;
            }
        }
        if ($bad) {
            return ['tone' => 'bad', 'label' => 'Erro HTTP'];
        }
        if ($warn) {
            return ['tone' => 'warn', 'label' => 'Atenção'];
        }

        return ['tone' => 'info', 'label' => 'Informativo'];
    }

    /**
     * @param array{tone: string, label: string, ratio: float} $pace
     * @return array<string, mixed>
     */
    private static function paceCard(array $pace, float $seconds): array
    {
        return [
            'key' => 'velocidade',
            'label' => 'Velocidade',
            'kind' => 'gauge',
            'tone' => $pace['tone'],
            'value' => self::compactSeconds($seconds),
            'ratio' => $pace['ratio'],
            'status' => $pace['label'],
            'extra' => 'Requisição HTTP',
            'aria' => 'Velocidade ' . self::compactSeconds($seconds) . ', ' . $pace['label'] . '. Classificação da requisição HTTP do VitSEO, não é Core Web Vitals.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function scoreCard(string $label, float $score, string $extra): array
    {
        $tone = self::scoreTone($score);

        return [
            'key' => 'seo',
            'label' => $label,
            'kind' => 'gauge',
            'tone' => $tone,
            'value' => Format::score($score),
            'ratio' => max(0, min(1, $score / 10)),
            'status' => self::scoreLabel($score),
            'extra' => $extra,
            'aria' => $label . ' ' . Format::score($score) . ' de 10, ' . self::scoreLabel($score),
        ];
    }

    /** @param float|null $score */
    private static function imageCard(AuditReport $report, mixed $score): array
    {
        $images = $report->images;
        $total = (int) ($images['total'] ?? 0);
        if ($score === null || $total === 0) {
            return [
                'key' => 'imagens',
                'label' => 'Imagens',
                'kind' => 'status',
                'tone' => 'info',
                'status' => 'Não avaliado',
                'extra' => 'Nenhuma imagem encontrada',
                'aria' => 'Imagens não avaliadas. Nenhuma imagem encontrada.',
            ];
        }
        $tone = self::scoreTone((float) $score);
        $missing = (int) ($images['sem_alt'] ?? 0);
        $extra = $missing > 0
            ? self::plural($missing, 'imagem sem alt', 'imagens sem alt')
            : $total . ' imagens verificadas';

        return [
            'key' => 'imagens',
            'label' => 'Imagens',
            'kind' => 'gauge',
            'tone' => $tone,
            'value' => Format::score((float) $score),
            'ratio' => max(0, min(1, ((float) $score) / 10)),
            'status' => self::scoreLabel((float) $score),
            'extra' => $extra,
            'aria' => 'Imagens ' . Format::score((float) $score) . ' de 10, ' . self::scoreLabel((float) $score) . '. ' . $extra,
        ];
    }

    /** @param array<string, mixed> $mobile */
    private static function mobileCard(array $mobile): array
    {
        $status = (string) ($mobile['status'] ?? '');
        if ($status === 'Viewport ausente') {
            return self::statusCard('mobile', 'Mobile', 'bad', 'Problema', 'Viewport ausente');
        }
        if ($status === 'Risco potencial') {
            return self::statusCard('mobile', 'Mobile', 'warn', 'Risco potencial', 'Larguras fixas encontradas');
        }

        return self::statusCard('mobile', 'Mobile', 'ok', 'OK', 'Nenhum problema estrutural evidente');
    }

    /** @param array<string, mixed> $geo */
    private static function geoCard(array $geo): array
    {
        $robots = !empty($geo['robots']['found']);
        $schema = ($geo['schema_types'] ?? []) !== [];
        if ($robots && $schema) {
            $extra = 'robots e schema verificados';
        } elseif ($robots) {
            $extra = 'robots.txt acessível';
        } else {
            $extra = 'Sinais públicos consultados';
        }

        return self::statusCard('geo', 'GEO', 'info', 'Informativo', $extra);
    }

    /** @param array<string, mixed> $eeat */
    private static function eeatCard(array $eeat): array
    {
        $count = self::eeatSignals($eeat);
        if ($count > 0) {
            return self::statusCard('eeat', 'E-E-A-T', 'ok', 'Sinais encontrados', self::plural($count, 'sinal detectado', 'sinais detectados'));
        }

        return self::statusCard('eeat', 'E-E-A-T', 'info', 'Informativo', 'Nenhum sinal objetivo detectado');
    }

    /** @param array<string, mixed> $analytics */
    private static function analyticsCard(array $analytics): array
    {
        $count = count($analytics['tags'] ?? []);
        if ($count > 0) {
            return self::statusCard(
                'analytics',
                'Analytics',
                'ok',
                'Tag detectada',
                self::plural($count, 'tecnologia encontrada', 'tecnologias encontradas')
            );
        }

        return self::statusCard('analytics', 'Analytics', 'info', 'Informativo', 'Nenhuma tag detectada');
    }

    /** @return array<string, mixed> */
    private static function statusCard(string $key, string $label, string $tone, string $status, string $extra): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'kind' => 'status',
            'tone' => $tone,
            'status' => $status,
            'extra' => $extra,
            'aria' => $label . ', ' . $status . '. ' . $extra,
        ];
    }

    private static function seoExtra(AuditReport $report): string
    {
        foreach ($report->findings as $finding) {
            if ($finding->category === 'seo' && $finding->classification === 'comprovado' && in_array($finding->severity, ['critico', 'importante'], true)) {
                return $finding->title;
            }
        }

        return 'Estrutura principal correta';
    }

    /** @param array<string, mixed> $eeat */
    private static function eeatSignals(array $eeat): int
    {
        $count = 0;
        foreach (['about', 'contact', 'privacy', 'terms'] as $key) {
            if (($eeat['pages'][$key]['status'] ?? '') === 'detectado') {
                $count++;
            }
        }
        foreach (['author', 'testimonials', 'portfolio'] as $key) {
            if (($eeat[$key]['status'] ?? '') === 'detectado') {
                $count++;
            }
        }
        if (($eeat['phones'] ?? []) !== []) {
            $count++;
        }
        if (($eeat['emails'] ?? []) !== []) {
            $count++;
        }

        return $count;
    }

    /** @return array{label: string, tone: string} */
    private static function presenceMark(string $label, bool $present): array
    {
        return [
            'label' => $present ? $label : $label . ' não detectado',
            'tone' => $present ? 'ok' : 'info',
        ];
    }

    private static function plural(int $count, string $one, string $many): string
    {
        return $count . ' ' . ($count === 1 ? $one : $many);
    }

    /**
     * Mesmos achados da aba Problemas, restritos a correções comprovadas.
     * Informativo, recomendação, não verificável e risco potencial ficam de fora.
     *
     * @param list<Finding> $findings
     * @return list<Finding>
     */
    public static function actionableFindings(array $findings): array
    {
        $selected = [];
        foreach (['critico', 'importante', 'melhoria'] as $severity) {
            foreach ($findings as $finding) {
                if ($finding->classification === 'comprovado' && $finding->severity === $severity) {
                    $selected[] = $finding;
                }
            }
        }

        return $selected;
    }

    /**
     * @param list<Finding> $findings
     */
    public static function aiPrompt(string $url, array $findings): ?string
    {
        $items = self::actionableFindings($findings);
        if ($items === []) {
            return null;
        }

        $lines = [
            'Foi realizada uma auditoria deste site utilizando o VitSEO.',
            '',
            'Analise o projeto existente e corrija somente os problemas comprovados listados abaixo.',
            '',
            'SITE ANALISADO:',
            $url,
            '',
            'REGRAS IMPORTANTES:',
            '',
            '- Trabalhe sobre o projeto existente.',
            '- Não recrie o site do zero.',
            '- Preserve design, conteúdo e funcionalidades que não estejam relacionados aos problemas encontrados.',
            '- Não faça alterações apenas por preferência estética.',
            '- Antes de alterar o código, confirme se cada problema ainda existe.',
            '- Caso algum item já esteja corrigido no código atual, não faça alteração desnecessária.',
            '- Não invente novos problemas.',
            '- Faça alterações conservadoras.',
            '- Evite regressões.',
            '- Preserve responsividade e funcionalidades existentes.',
            '',
            'PROBLEMAS IDENTIFICADOS PELO VITSEO:',
            '',
        ];

        foreach ($items as $index => $finding) {
            $lines[] = ($index + 1) . '. ' . $finding->title;
            $lines[] = '';
            $lines[] = 'Categoria:';
            $lines[] = self::categoryLabel($finding->category);
            $lines[] = '';
            $lines[] = 'Severidade:';
            $lines[] = self::severityLabel($finding->severity);
            $lines[] = '';
            $lines[] = 'Evidência:';
            $lines[] = $finding->evidence;
            if ($finding->recommendation !== null && $finding->recommendation !== '') {
                $lines[] = '';
                $lines[] = 'Correção recomendada:';
                $lines[] = $finding->recommendation;
            }
            $lines[] = '';
        }

        $lines[] = 'Após realizar os ajustes:';
        $lines[] = '';
        $lines[] = '1. informe quais problemas foram corrigidos;';
        $lines[] = '2. informe quais arquivos foram alterados;';
        $lines[] = '3. informe quais problemas não puderam ser corrigidos e o motivo;';
        $lines[] = '4. informe quais testes foram realizados;';
        $lines[] = '5. informe qualquer risco de regressão identificado.';
        $lines[] = '';
        $lines[] = 'Não faça commit, push ou deploy sem autorização.';

        return implode("\n", $lines);
    }

    private static function categoryLabel(string $category): string
    {
        return match ($category) {
            'seo' => 'SEO',
            'velocidade' => 'Velocidade',
            'imagens' => 'Imagens',
            'mobile' => 'Mobile',
            'geo' => 'GEO',
            'eeat' => 'E-E-A-T',
            'analytics' => 'Analytics',
            'subpaginas' => 'Subpáginas',
            'semantica' => 'Semântica',
            'palavra-chave' => 'Palavra-chave',
            default => 'Geral',
        };
    }

    private static function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critico' => 'Crítico',
            'importante' => 'Importante',
            'melhoria' => 'Melhoria',
            default => 'Informativo',
        };
    }
}
