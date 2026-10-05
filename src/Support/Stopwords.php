<?php

declare(strict_types=1);

namespace VitSeo\Support;

final class Stopwords
{
    /** @var list<string> */
    private const WORDS = [
        'a', 'o', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'no', 'na', 'nos', 'nas',
        'por', 'para', 'com', 'sem', 'sob', 'sobre', 'entre', 'ate', 'ao', 'aos', 'à', 'às', 'que', 'se', 'ou', 'mas', 'como',
        'mais', 'menos', 'muito', 'muitos', 'muita', 'muitas', 'pouco', 'já', 'ja', 'também', 'tambem', 'não', 'nao', 'sim',
        'eu', 'você', 'voce', 'ele', 'ela', 'eles', 'elas', 'nós', 'nos', 'vos', 'meu', 'minha', 'seu', 'sua', 'seus', 'suas',
        'este', 'esta', 'estes', 'estas', 'esse', 'essa', 'isso', 'isto', 'aquele', 'aquela', 'aquilo', 'the', 'and', 'or',
        'of', 'to', 'in', 'for', 'on', 'with', 'by', 'from', 'at', 'is', 'are', 'be', 'this', 'that', 'it', 'an',
        'ser', 'estar', 'foi', 'são', 'sao', 'era', 'tem', 'ter', 'há', 'ha', 'seu', 'sua', 'pelo', 'pela', 'pelos', 'pelas',
        'quando', 'onde', 'porque', 'porquê', 'qual', 'quais', 'quem', 'cujo', 'cuja', 'depois', 'antes', 'durante', 'apenas',
        'ainda', 'então', 'entao', 'assim', 'apenas', 'cada', 'outro', 'outra', 'outros', 'outras', 'mesmo', 'mesma', 'só', 'so',
        'já', 'aqui', 'ali', 'lá', 'la', 'bem', 'mal', 'pode', 'podem', 'deve', 'devem', 'vai', 'vão', 'vao', 'ir', 'vir',
        'http', 'https', 'www', 'com', 'br',
    ];

    public static function isStopword(string $token): bool
    {
        return in_array(Text::stripAccents($token), self::WORDS, true) || mb_strlen($token) < 3;
    }
}
