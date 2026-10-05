<?php

declare(strict_types=1);

use VitSeo\Analysis\EeatAnalyzer;
use VitSeo\Analysis\Finding;
use VitSeo\Analysis\GeoAnalyzer;
use VitSeo\Analysis\ImageAnalyzer;
use VitSeo\Analysis\ProblemAnalyzer;
use VitSeo\Analysis\ScoreCalculator;
use VitSeo\Analysis\SeoAnalyzer;
use VitSeo\Http\IpGuard;
use VitSeo\Http\UrlFetcher;
use VitSeo\Http\UrlValidator;
use VitSeo\Support\HtmlParser;
use VitSeo\Support\ReportUi;

require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/fakes.php';

$failures = 0;
$passes = 0;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test(string $name, callable $fn): void
{
    global $failures, $passes;
    try {
        $fn();
        $passes++;
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "FAIL {$name}\n  {$e->getMessage()}\n";
    }
}

function titles(array $findings): array
{
    return array_map(static fn (Finding $finding): string => $finding->title, $findings);
}

function find_title(array $findings, string $needle): ?Finding
{
    foreach ($findings as $finding) {
        if (str_contains($finding->title, $needle)) {
            return $finding;
        }
    }

    return null;
}

$seo = new SeoAnalyzer();
$images = new ImageAnalyzer();
$eeat = new EeatAnalyzer();
$scores = new ScoreCalculator(require dirname(__DIR__) . '/config/scoring.php');

test('H1 existente não é reportado como ausente', function () use ($seo): void {
    $url = 'https://exemplo.com.br/servicos';
    $html = '<!doctype html><html lang="pt-BR"><head><title>Serviços de criação de sites</title></head><body><div><section><h1>Serviços de criação</h1><p>Conteúdo da página.</p></section></div></body></html>';
    $result = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    check(find_title($result['findings'], 'H1 não encontrado') === null, 'H1 presente foi marcado como ausente: ' . implode(' | ', titles($result['findings'])));
    check($result['data']['h1']['count'] === 1, 'contagem de H1 deveria ser 1');
    check($result['data']['h1']['items'][0] === 'Serviços de criação', 'texto do H1 não foi lido');
});

test('Um único H1 fica ok', function () use ($seo): void {
    $url = 'https://exemplo.com.br/';
    $html = '<html><head><title>Página institucional da empresa</title></head><body><h1>Bem-vindo ao estúdio</h1></body></html>';
    $result = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    check(find_title($result['findings'], 'H1 não encontrado') === null, 'H1 único marcado como ausente');
    check(find_title($result['findings'], 'Mais de um H1') === null, 'H1 único marcado como múltiplo');
    check($result['data']['checks'][3]['tone'] === 'ok', 'checklist de H1 não ficou ok');
});

test('Múltiplos H1 são detectados sem penalização automática', function () use ($seo): void {
    $url = 'https://exemplo.com.br/';
    $html = '<html><head><title>Página com dois títulos principais</title></head><body><h1>Primeiro</h1><h1>Segundo</h1></body></html>';
    $result = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    $finding = find_title($result['findings'], 'Mais de um H1');
    check($finding instanceof Finding, 'múltiplos H1 não foram detectados');
    check($finding->classification === 'comprovado', 'classificação deveria ser comprovado');
    check($finding->severity === 'melhoria', 'severidade não deveria ser crítica');
    check(str_contains($finding->explanation, 'não indica'), 'explicação deveria deixar claro que não há punição automática');
    check(!str_contains(mb_strtolower($finding->explanation), 'será penaliz'), 'explicação afirma penalização');
});

test('alt vazio com aria-hidden é decorativo', function () use ($images): void {
    $url = 'https://exemplo.com.br/';
    $html = '<html><body><img src="/decor.svg" alt="" aria-hidden="true"><div aria-hidden="true"><img src="/ico.svg" alt=""></div><p>Texto.</p></body></html>';
    $result = $images->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    check($result['data']['decorativas'] === 2, 'decorativas = ' . $result['data']['decorativas']);
    check($result['data']['sem_alt'] === 0, 'decorativa entrou como sem alt');
    check(find_title($result['findings'], 'sem atributo alt') === null, 'decorativa virou erro de alt');
});

test('alt vazio sem contexto decorativo pede revisão', function () use ($images): void {
    $url = 'https://exemplo.com.br/';
    $html = '<html><body><img src="/foto.jpg" alt=""></body></html>';
    $result = $images->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    $finding = find_title($result['findings'], 'revisão manual');
    check($finding instanceof Finding, 'alt vazio não gerou revisão');
    check($finding->classification === 'recomendacao', 'deveria ser recomendação');
    check(find_title($result['findings'], 'sem atributo alt') === null, 'alt vazio foi tratado como ausência do atributo');
});

test('imagem informativa sem alt é comprovada', function () use ($images): void {
    $url = 'https://exemplo.com.br/';
    $html = '<html><body><img src="/equipe.jpg" width="800" height="600"></body></html>';
    $result = $images->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    $finding = find_title($result['findings'], 'sem atributo alt');
    check($finding instanceof Finding, 'imagem sem alt não foi detectada');
    check($finding->classification === 'comprovado', 'deveria ser comprovado');
    check($finding->severity === 'importante', 'deveria ser importante');
    check(str_contains($finding->evidence, '/equipe.jpg'), 'evidência sem o src');
});

test('canonical auto-referente é reconhecido', function () use ($seo): void {
    $url = 'https://exemplo.com.br/pagina';
    $html = '<html><head><link rel="canonical" href="https://exemplo.com.br/pagina"><title>Página canônica de exemplo</title></head><body><h1>Página</h1></body></html>';
    $result = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    check($result['data']['canonical']['self'] === true, 'canonical idêntico não foi auto-referente');
    check(find_title($result['findings'], 'Canonical aponta') === null, 'canonical idêntico foi marcado como diferente');

    $slash = 'https://exemplo.com.br/pagina/';
    $htmlSlash = '<html><head><link rel="canonical" href="https://exemplo.com.br/pagina"></head><body><h1>Página</h1></body></html>';
    $slashResult = $seo->analyze(new HtmlParser($htmlSlash, $slash), fetch_of($slash, $htmlSlash));
    check($slashResult['data']['canonical']['self'] === true, 'barra final impediu reconhecer o canonical');
});

test('canonical diferente é evidência e não é crítico', function () use ($seo, $scores): void {
    $url = 'https://exemplo.com.br/pasta/item';
    $html = '<html><head><link rel="canonical" href="/outra"><title>Item de exemplo para canonical</title></head><body><h1>Item</h1></body></html>';
    $result = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    $finding = find_title($result['findings'], 'Canonical aponta');
    check($finding instanceof Finding, 'canonical diferente não apareceu');
    check($finding->classification === 'comprovado', 'deveria ser comprovado');
    check($finding->severity === 'informativo', 'não deveria ser crítico');
    check(str_contains($finding->evidence, 'https://exemplo.com.br/outra'), 'URL do canonical ausente na evidência');
    check(str_contains($finding->evidence, $url), 'URL analisada ausente na evidência');
    check($scores->calculate([$finding])['score'] === 10.0, 'canonical diferente reduziu a nota');
});

test('noindex em meta e header é detectado', function () use ($seo): void {
    $url = 'https://exemplo.com.br/privada';
    $html = '<html><head><meta name="robots" content="noindex, nofollow"><title>Página privada de exemplo</title></head><body><h1>Privada</h1></body></html>';
    $meta = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    $finding = find_title($meta['findings'], 'noindex');
    check($finding instanceof Finding, 'noindex da meta não foi detectado');
    check($finding->classification === 'comprovado', 'noindex deveria ser comprovado');
    check(str_contains($finding->evidence, 'noindex'), 'evidência sem a diretiva');

    $plain = '<html><head><title>Outra página privada</title></head><body><h1>Privada</h1></body></html>';
    $header = $seo->analyze(new HtmlParser($plain, $url), fetch_of($url, $plain, ['x-robots-tag' => 'noindex']));
    check(find_title($header['findings'], 'noindex') instanceof Finding, 'X-Robots-Tag noindex não foi detectado');
});

test('JSON-LD válido lista os @type', function () use ($seo): void {
    $url = 'https://exemplo.com.br/';
    $html = '<html><head><title>Página com dados estruturados</title><script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"WebPage","name":"Home"},{"@type":"Organization","name":"Exemplo"}]}</script></head><body><h1>Home</h1></body></html>';
    $result = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    check(in_array('WebPage', $result['data']['schema']['types'], true), 'WebPage não listado');
    check(in_array('Organization', $result['data']['schema']['types'], true), 'Organization não listado');
    check(find_title($result['findings'], 'JSON-LD inválido') === null, 'JSON válido marcado como inválido');
});

test('Open Graph e Twitter Cards são lidos e a ausência não é erro', function () use ($seo, $scores): void {
    $url = 'https://exemplo.com.br/';
    $present = '<html><head><title>Página com compartilhamento social</title>'
        . '<meta property="og:title" content="Título social">'
        . '<meta property="og:description" content="Descrição social">'
        . '<meta property="og:image" content="https://exemplo.com.br/og.jpg">'
        . '<meta property="og:url" content="https://exemplo.com.br/">'
        . '<meta property="og:type" content="website">'
        . '<meta property="og:site_name" content="Exemplo">'
        . '<meta name="twitter:card" content="summary_large_image">'
        . '<meta name="twitter:title" content="Título no X">'
        . '<meta name="twitter:image" content="https://exemplo.com.br/x.jpg">'
        . '</head><body><h1>Página</h1></body></html>';
    $result = $seo->analyze(new HtmlParser($present, $url), fetch_of($url, $present));
    check($result['data']['open_graph']['og:title'] === 'Título social', 'og:title não foi lido');
    check($result['data']['open_graph']['og:image'] === 'https://exemplo.com.br/og.jpg', 'og:image não foi lido');
    check($result['data']['open_graph']['og:site_name'] === 'Exemplo', 'og:site_name presente não entrou na exibição');
    check($result['data']['twitter']['twitter:card'] === 'summary_large_image', 'twitter:card não foi lido');
    check($result['data']['twitter']['twitter:title'] === 'Título no X', 'twitter:title não foi lido');
    check(find_title($result['findings'], 'Open Graph incompleto') === null, 'Open Graph completo foi marcado como incompleto');

    $bare = '<html><head><title>Página sem compartilhamento social</title></head><body><h1>Página</h1></body></html>';
    $absent = $seo->analyze(new HtmlParser($bare, $url), fetch_of($url, $bare));
    $graph = find_title($absent['findings'], 'Open Graph incompleto');
    $cards = find_title($absent['findings'], 'Twitter Cards não declarados');
    check($graph instanceof Finding && $graph->classification === 'recomendacao', 'ausência de Open Graph virou erro comprovado');
    check($cards instanceof Finding && $cards->classification === 'informativo' && $cards->severity === 'informativo', 'ausência de Twitter Cards virou erro');
    check($scores->calculate([$graph, $cards])['proven_deduction'] === 0.0, 'ausência social descontou como erro comprovado');
    $prompt = ReportUi::aiPrompt($url, $absent['findings']);
    check($prompt === null || (!str_contains($prompt, 'Open Graph') && !str_contains($prompt, 'Twitter Cards')), 'ausência social entrou no prompt');
});

test('Schema mostra tipos e propriedades e só o JSON inválido é comprovado', function () use ($seo): void {
    $url = 'https://exemplo.com.br/artigo';
    $html = '<html><head><title>Página com dados estruturados completos</title>'
        . '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"Como auditar","description":"Texto do artigo","image":{"@type":"ImageObject","url":"https://exemplo.com.br/capa.jpg"},"author":{"@type":"Person","name":"Ana"}}</script>'
        . '</head><body><h1>Artigo</h1></body></html>';
    $result = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    foreach (['Article', 'ImageObject', 'Person'] as $type) {
        check(in_array($type, $result['data']['schema']['types'], true), $type . ' não foi detectado');
    }
    $article = null;
    foreach ($result['data']['schema']['entities'] as $entity) {
        if ($entity['type'] === 'Article') {
            $article = $entity;
        }
    }
    check(is_array($article), 'entidade Article não foi listada');
    $props = [];
    foreach ($article['properties'] as $property) {
        $props[$property['name']] = $property['value'];
    }
    check(($props['headline'] ?? '') === 'Como auditar', 'headline não foi extraído');
    check(($props['image'] ?? '') === 'https://exemplo.com.br/capa.jpg', 'imagem do schema não foi extraída');
    check(($props['author'] ?? '') === 'Ana', 'autor do schema não foi extraído');
    check($result['data']['schema']['invalid'] === [], 'JSON válido gerou erro de parse');
    check(ReportUi::aiPrompt($url, $result['findings']) === null || !str_contains((string) ReportUi::aiPrompt($url, $result['findings']), 'JSON-LD'), 'schema válido entrou no prompt como erro');

    $broken = '<html><head><title>Página com JSON quebrado e outro válido</title><script type="application/ld+json">{invalid</script></head><body><h1>Página</h1></body></html>';
    $invalid = $seo->analyze(new HtmlParser($broken, $url), fetch_of($url, $broken));
    $finding = find_title($invalid['findings'], 'JSON-LD inválido');
    check($finding instanceof Finding && $finding->classification === 'comprovado' && $finding->severity === 'importante', 'JSON inválido deixou de ser problema comprovado');
    $prompt = ReportUi::aiPrompt($url, $invalid['findings']);
    check(is_string($prompt) && str_contains($prompt, 'JSON-LD inválido'), 'JSON inválido não entrou no prompt');
    check(!str_contains($prompt, 'Nenhum JSON-LD'), 'ausência de schema entrou no prompt junto do erro real');
});

test('JSON-LD inválido é detectado', function () use ($seo): void {
    $url = 'https://exemplo.com.br/';
    $html = '<html><head><title>Página com JSON quebrado</title><script type="application/ld+json">{invalid</script></head><body><h1>Home</h1></body></html>';
    $result = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    $finding = find_title($result['findings'], 'JSON-LD inválido');
    check($finding instanceof Finding, 'JSON inválido não foi detectado');
    check($finding->classification === 'comprovado', 'deveria ser comprovado');
});

test('WhatsApp não vira página Sobre', function () use ($eeat): void {
    $url = 'https://exemplo.com.br/';
    $html = '<html><body><a href="https://wa.me/5511999999999">Sobre</a><a href="https://api.whatsapp.com/send?phone=5511">Quem somos</a></body></html>';
    $result = $eeat->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    $about = $result['data']['pages']['about'];
    check($about['status'] !== 'detectado', 'WhatsApp foi identificado como Sobre');
    check($about['url'] === null || !str_contains((string) $about['url'], 'wa.me'), 'URL de Sobre aponta para WhatsApp');
    check($about['url'] === null || !str_contains((string) $about['url'], 'whatsapp'), 'URL de Sobre aponta para WhatsApp');

    $real = '<html><body><a href="/sobre">Sobre</a></body></html>';
    $positive = $eeat->analyze(new HtmlParser($real, $url), fetch_of($url, $real));
    check($positive['data']['pages']['about']['status'] === 'detectado', 'link /sobre não foi reconhecido');
    check(str_contains((string) $positive['data']['pages']['about']['url'], '/sobre'), 'evidência sem a URL /sobre');
});

test('ausência de llms.txt não é erro crítico', function () use ($scores): void {
    $url = 'https://exemplo.com.br/pagina';
    $html = '<html><body><main><h1>Página</h1><p>Conteúdo rastreável da página de exemplo.</p></main></body></html>';
    $map = [];
    foreach (['/robots.txt', '/llms.txt', '/llms-full.txt', '/sitemap.xml', '/sitemap_index.xml'] as $path) {
        $map['https://exemplo.com.br' . $path] = http_document('https://exemplo.com.br' . $path, 404);
    }
    $fetcher = new MapFetcher($map);
    $result = (new GeoAnalyzer($fetcher, []))->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    foreach ($result['findings'] as $finding) {
        check($finding->severity !== 'critico', 'achado crítico indevido: ' . $finding->title);
    }
    $llms = find_title($result['findings'], 'llms');
    check($llms instanceof Finding, 'llms.txt ausente não foi registrado');
    check($llms->severity === 'informativo', 'llms.txt ausente não ficou informativo');
    check($llms->classification === 'informativo', 'classificação de llms incorreta');
    check(str_contains($llms->explanation, 'não comprova'), 'explicação não limita o significado da ausência');
    check($scores->calculate([$llms])['score'] === 10.0, 'ausência de llms reduziu a nota');
});

test('localhost, IP privado e protocolos são bloqueados', function (): void {
    $validator = new UrlValidator(new ExplodingDns());
    $blocked = [
        'http://localhost/admin',
        'http://127.0.0.1/',
        'http://127.0.0.8/painel',
        'http://10.1.2.3/',
        'http://192.168.0.20/',
        'http://172.16.5.5/',
        'http://169.254.169.254/latest/meta-data',
        'http://[::1]/',
        'http://2130706433/',
        'http://0177.0.0.1/',
        'file:///etc/passwd',
        'ftp://example.com/arquivo',
        'gopher://example.com/',
        'data:text/html,teste',
    ];
    foreach ($blocked as $url) {
        $result = $validator->validate($url);
        check(!$result->ok, $url . ' deveria ser bloqueada');
        check(in_array($result->errorCode, ['blocked', 'invalid_url'], true), $url . ' código ' . $result->errorCode);
    }

    $transport = new FakeTransport([]);
    $fetcher = new UrlFetcher($validator, $transport, ['max_redirects' => 5, 'timeout' => 5, 'max_bytes' => 1000]);
    $local = $fetcher->fetch('http://localhost/secreto');
    check(!$local->ok && $local->errorCode === 'blocked', 'fetcher aceitou localhost');
    check($transport->calls === [], 'localhost chegou a gerar requisição');
    $file = $fetcher->fetch('file:///C:/segredo');
    check(!$file->ok && $file->errorCode === 'blocked', 'file:// não foi bloqueado');
    check($transport->calls === [], 'file:// chegou a gerar requisição');
});

test('DNS privado e redirect para IP privado são bloqueados', function (): void {
    $dns = new FakeDns([
        'example.com' => ['93.184.216.34'],
        'evil.example' => ['10.0.0.8'],
    ]);
    $validator = new UrlValidator($dns);
    $evil = $validator->validate('https://evil.example/painel');
    check(!$evil->ok && $evil->errorCode === 'blocked', 'domínio com IP privado passou');

    $transport = new FakeTransport([
        'https://example.com/' => http_response(302, ['location' => 'http://127.0.0.1/secret']),
    ]);
    $fetcher = new UrlFetcher($validator, $transport, ['max_redirects' => 5, 'timeout' => 5, 'max_bytes' => 1000]);
    $redirect = $fetcher->fetch('https://example.com/');
    check(!$redirect->ok && $redirect->errorCode === 'blocked', 'redirect para IP privado passou: ' . $redirect->message);
    check($transport->calls === ['https://example.com/'], 'destino privado foi requisitado: ' . implode(', ', $transport->calls));

    check(IpGuard::isBlocked('100.64.1.1'), 'CGNAT deveria ser bloqueado');
    check(!IpGuard::isBlocked('8.8.8.8'), 'IP público foi bloqueado');
    check(!IpGuard::isBlocked('172.32.1.1'), '172.32 está fora da faixa privada');
    check($validator->validate('https://8.8.8.8/')->ok, 'IP público literal foi recusado');
});

test('nota não penaliza informativo e limita recomendações', function () use ($scores): void {
    $info = new Finding('llms.txt não encontrado', 'informativo', 'informativo', 'ausente', 'não comprova', null, 'geo');
    $unknown = new Finding('Autoria', 'informativo', 'nao_verificavel', 'sem marca', 'incerto', null, 'eeat');
    check($scores->calculate([$info, $unknown])['score'] === 10.0, 'informativo ou não verificável reduziu a nota');

    $recommendations = [];
    for ($i = 0; $i < 30; $i++) {
        $recommendations[] = new Finding('Ajuste ' . $i, 'melhoria', 'recomendacao', 'exemplo', 'oportunidade', null, 'seo');
    }
    $capped = $scores->calculate($recommendations);
    check($capped['score'] === 9.0, 'teto de recomendação falhou: ' . $capped['score']);

    $critical = new Finding('Status HTTP 500', 'critico', 'comprovado', 'HTTP 500', 'falha', null, 'velocidade');
    check($scores->calculate([$critical])['score'] === 8.5, 'crítico comprovado não descontou 1,5');
    $risk = new Finding('Largura fixa', 'melhoria', 'risco_potencial', '960px', 'indício', null, 'mobile');
    check($scores->calculate([$risk])['score'] === 9.9, 'desconto pequeno desapareceu na nota exibida');
});

test('faixas visuais não alteram a nota e não tratam llms como erro', function (): void {
    check(ReportUi::scoreTone(10.0) === 'ok', 'nota 10 deveria ser verde');
    check(ReportUi::scoreTone(8.0) === 'ok', 'nota 8 deveria ser verde');
    check(ReportUi::scoreTone(7.9) === 'warn', 'nota 7,9 deveria ser amarela');
    check(ReportUi::scoreTone(6.0) === 'warn', 'nota 6 deveria ser amarela');
    check(ReportUi::scoreTone(5.9) === 'bad', 'nota 5,9 deveria ser vermelha');

    check(ReportUi::httpPace(0.41)['label'] === 'Excelente', '0,41 s não ficou excelente');
    check(ReportUi::httpPace(1.5)['label'] === 'Excelente', '1,5 s deveria continuar excelente');
    check(ReportUi::httpPace(1.51)['label'] === 'Bom', '1,51 s deveria ser bom');
    check(ReportUi::httpPace(2.5)['label'] === 'Bom', '2,5 s deveria ser bom');
    check(ReportUi::httpPace(2.51)['label'] === 'Regular', '2,51 s deveria ser regular');
    check(ReportUi::httpPace(4.5)['label'] === 'Regular', '4,5 s deveria ser regular');
    check(ReportUi::httpPace(4.51)['label'] === 'Lento', '4,51 s deveria ser lento');
    check(ReportUi::httpPace(0.41)['tone'] !== 'bad', 'tempo baixo não pode parecer erro');

    $marks = ReportUi::geoMarks([
        'robots' => ['found' => true],
        'sitemaps' => [['found' => true]],
        'schema_types' => ['Organization'],
        'llms' => [
            'llms.txt' => ['state' => 'nao_encontrado'],
            'llms-full.txt' => ['state' => 'nao_encontrado'],
        ],
    ]);
    foreach ($marks as $mark) {
        if (str_contains($mark['label'], 'llms')) {
            check($mark['tone'] === 'info', 'llms ausente virou ' . $mark['tone']);
        }
    }
    $seo = ReportUi::seoMarks([
        'title' => ['present' => true],
        'description' => ['present' => true],
        'h1' => ['count' => 1],
        'canonical' => ['present' => true, 'self' => true],
        'robots' => ['noindex' => false],
        'schema' => ['types' => ['Organization'], 'invalid' => []],
    ]);
    $byLabel = [];
    foreach ($seo as $mark) {
        $byLabel[$mark['label']] = $mark['tone'];
    }
    check($byLabel['H1'] === 'ok', 'H1 único não ficou positivo');
    check($byLabel['Canonical'] === 'ok', 'canonical self não ficou positivo');
    $different = ReportUi::seoMarks([
        'title' => ['present' => true],
        'description' => ['present' => true],
        'h1' => ['count' => 1],
        'canonical' => ['present' => true, 'self' => false],
        'robots' => ['noindex' => false],
        'schema' => ['types' => [], 'invalid' => []],
    ]);
    foreach ($different as $mark) {
        if ($mark['label'] === 'Canonical') {
            check($mark['tone'] === 'info', 'canonical diferente virou ' . $mark['tone']);
        }
    }
    $eeat = ReportUi::eeatMarks([
        'pages' => [
            'contact' => ['status' => 'detectado'],
            'privacy' => ['status' => 'detectado'],
            'about' => ['status' => 'nao_detectado'],
            'terms' => ['status' => 'nao_detectado'],
        ],
        'phones' => ['11999999999'],
        'emails' => [],
        'cnpj' => ['detected' => false],
    ]);
    $eeatLabels = array_column($eeat, 'label', 'label');
    check(isset($eeatLabels['Sobre não encontrada']), 'sobre ausente deveria continuar informativo');
    check(isset($eeatLabels['Termos não encontrados']), 'termos ausentes ficaram com concordância errada');
    check(isset($eeatLabels['CNPJ não detectado']), 'CNPJ ausente deveria continuar informativo');
    foreach ($eeat as $mark) {
        if (str_contains($mark['label'], 'Sobre') || str_contains($mark['label'], 'CNPJ')) {
            check($mark['tone'] === 'info', $mark['label'] . ' virou ' . $mark['tone']);
        }
    }

    $clear = ReportUi::verdict(0, 0);
    check($clear['tone'] === 'ok', 'página sem crítico/importante não ficou positiva');
    check(ReportUi::verdict(1, 0)['tone'] === 'bad', 'crítico não ficou vermelho');
    check(ReportUi::verdict(0, 2)['tone'] === 'warn', 'importante não ficou em atenção');
});

test('ausência de H1, description e canonical não é correção obrigatória', function () use ($seo, $scores): void {
    $url = 'https://exemplo.com.br/pagina';
    $html = '<html><head><title>Página de exemplo com título suficiente</title></head><body><p>Texto da página.</p></body></html>';
    $result = $seo->analyze(new HtmlParser($html, $url), fetch_of($url, $html));
    $expected = [
        'H1 não encontrado' => 'Um H1 claro pode melhorar a estrutura semântica',
        'Meta description ausente' => 'pode gerar o snippet automaticamente',
        'Canonical ausente' => 'pode funcionar sem canonical explícito',
    ];
    $absent = [];
    foreach ($expected as $title => $explanation) {
        $finding = find_title($result['findings'], $title);
        check($finding instanceof Finding, $title . ' deixou de ser detectado');
        if (!$finding instanceof Finding) {
            continue;
        }
        $absent[] = $finding;
        check($finding->classification === 'recomendacao', $title . ' continuou como ' . $finding->classification);
        check($finding->severity === 'melhoria', $title . ' continuou como ' . $finding->severity);
        check(str_contains($finding->explanation, $explanation), $title . ' perdeu a explicação de recomendação');
        check($finding->evidence !== '', $title . ' ficou sem evidência');
    }
    check($result['data']['checks'][2]['tone'] === 'attention', 'description ausente ficou como erro no checklist');
    check($result['data']['checks'][3]['tone'] === 'attention', 'H1 ausente ficou como erro no checklist');
    check($result['data']['checks'][5]['tone'] === 'attention', 'canonical ausente ficou como erro no checklist');

    $groups = (new ProblemAnalyzer())->group($result['findings']);
    foreach (array_keys($expected) as $title) {
        check(find_title($groups['melhoria'], $title) instanceof Finding, $title . ' saiu do grupo de melhorias do relatório');
        check(find_title($groups['importante'], $title) === null, $title . ' continuou entre os importantes');
        check(find_title($groups['critico'], $title) === null, $title . ' entrou entre os críticos');
    }

    $scored = $scores->calculate($absent);
    check($scored['proven_deduction'] === 0.0, 'ausência simples ainda desconta como erro comprovado');
    check($scored['recommendation_deduction'] === 0.18, 'recomendação/melhoria não usou 0,06 por item: ' . $scored['recommendation_deduction']);

    $prompt = ReportUi::aiPrompt($url, $result['findings']);
    check($prompt === null, 'ausências simples geraram prompt de correção');
    $selected = array_map(static fn (Finding $finding): string => $finding->title, ReportUi::actionableFindings($result['findings']));
    check($selected === [], 'o filtro do botão incluiu ausência simples: ' . implode(' | ', $selected));

    $broken = '<html><head></head><body><h1>Página</h1><img src="/foto.jpg" width="800" height="600"></body></html>';
    $proven = $seo->analyze(new HtmlParser($broken, $url), fetch_of($url, $broken));
    $prompt = ReportUi::aiPrompt($url, $proven['findings']);
    check(is_string($prompt) && str_contains($prompt, 'Title ausente'), 'title ausente deixou de entrar no prompt');
    check(!str_contains((string) $prompt, 'H1 não encontrado'), 'H1 presente entrou como ausência');
    check(!str_contains((string) $prompt, 'Meta description ausente'), 'description ausente entrou junto de um erro real');
    check(!str_contains((string) $prompt, 'Canonical ausente'), 'canonical ausente entrou junto de um erro real');
});

test('prompt de IA usa só problemas comprovados', function (): void {
    $findings = [
        new Finding('Title ausente', 'importante', 'comprovado', 'Não há elemento <title> com texto.', 'O título não foi encontrado.', 'Defina um title.', 'seo'),
        new Finding('Larguras fixas grandes no código', 'melhoria', 'risco_potencial', '960px', 'Indício, não prova visual.', null, 'mobile'),
        new Finding('llms.txt não encontrado', 'informativo', 'informativo', 'ausente', 'Não comprova perda de visibilidade.', null, 'geo'),
        new Finding('Comprimento do title fora da faixa usual', 'melhoria', 'recomendacao', 'Title com 80 caracteres.', 'Não é regra absoluta.', null, 'seo'),
        new Finding('Canonical aponta para outra URL', 'informativo', 'comprovado', 'Canonical diferente.', 'Pode ser intencional.', null, 'seo'),
        new Finding('CNPJ não detectado', 'informativo', 'informativo', 'Nenhum CNPJ.', 'Não é requisito de ranking.', null, 'eeat'),
        new Finding('Imagem informativa sem atributo alt', 'importante', 'comprovado', '1 imagem sem o atributo alt. Exemplos: /foto.jpg', 'O atributo alt não existe.', 'Inclua alt quando a imagem transmitir conteúdo.', 'imagens'),
    ];

    $selected = ReportUi::actionableFindings($findings);
    $titles = array_map(static fn (Finding $finding): string => $finding->title, $selected);
    check($titles === ['Title ausente', 'Imagem informativa sem atributo alt'], 'o prompt incluiu item que não é correção comprovada: ' . implode(' | ', $titles));

    $prompt = ReportUi::aiPrompt('https://exemplo.com.br/pagina', $findings);
    check(is_string($prompt), 'prompt não foi gerado');
    check(str_contains($prompt, 'SITE ANALISADO:' . "\n" . 'https://exemplo.com.br/pagina'), 'URL analisada ausente');
    check(str_contains($prompt, '1. Title ausente'), 'title ausente não entrou');
    check(str_contains($prompt, "Categoria:\nSEO"), 'categoria ausente');
    check(str_contains($prompt, "Severidade:\nImportante"), 'severidade ausente');
    check(str_contains($prompt, "Evidência:\nNão há elemento <title> com texto."), 'evidência ausente');
    check(str_contains($prompt, "Correção recomendada:\nDefina um title."), 'correção ausente');
    check(str_contains($prompt, '2. Imagem informativa sem atributo alt'), 'imagem sem alt não entrou');
    check(str_contains($prompt, '/foto.jpg'), 'evidência da imagem ausente');
    foreach (['Larguras fixas', 'llms.txt', 'faixa usual', 'Canonical aponta', 'CNPJ não detectado'] as $excluded) {
        check(!str_contains($prompt, $excluded), $excluded . ' entrou no prompt como erro');
    }
    check(ReportUi::aiPrompt('https://exemplo.com.br/', [
        new Finding('llms.txt não encontrado', 'informativo', 'informativo', 'ausente', 'não comprova', null, 'geo'),
        new Finding('Largura fixa', 'melhoria', 'risco_potencial', '960px', 'indício', null, 'mobile'),
    ]) === null, 'prompt foi gerado sem problema comprovado');
});

echo "\n{$passes} passaram, {$failures} falharam.\n";
exit($failures > 0 ? 1 : 0);
