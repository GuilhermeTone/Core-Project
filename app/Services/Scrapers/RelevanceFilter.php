<?php

namespace App\Services\Scrapers;

/**
 * Filters and ranks scraper results by relevance to the original search term.
 *
 * Scoring strategy (0.0 – 1.0):
 *   score = matched_query_tokens / total_query_tokens
 *
 * Token matching considers:
 *   - Exact match after normalization
 *   - Plural/singular variation (strip trailing 's')
 *   - Known domain synonyms (via QueryNormalizer::sinonimos)
 *   - Fuzzy similarity for longer words (similar_text >= 85%)
 *
 * Results below THRESHOLD are discarded; the rest are sorted by score descending.
 */
class RelevanceFilter
{
    /** Minimum fraction of query tokens that must appear in a result name. */
    private const THRESHOLD = 0.5;

    /**
     * Filters $resultados to those relevant to $termo, sorted by score descending.
     * If the term produces no tokens (too short / all stopwords), returns as-is.
     *
     * @param  array<array{nome: string, ...}> $resultados
     * @return array<array{nome: string, ...}>
     */
    public static function filtrar(array $resultados, string $termo): array
    {
        $queryTokens = QueryNormalizer::tokenizar($termo);

        if (empty($queryTokens)) {
            return $resultados;
        }

        $pontuados = [];

        foreach ($resultados as $resultado) {
            $score = self::pontuar($resultado['nome'] ?? '', $queryTokens);

            if ($score >= self::THRESHOLD) {
                $pontuados[] = ['r' => $resultado, 's' => $score];
            }
        }

        usort($pontuados, fn ($a, $b) => $b['s'] <=> $a['s']);

        return array_column($pontuados, 'r');
    }

    /**
     * Returns a relevance score [0.0, 1.0] for a product name against the query tokens.
     *
     * @param string[] $queryTokens
     */
    public static function pontuar(string $nome, array $queryTokens): float
    {
        if (empty($nome) || empty($queryTokens)) {
            return 0.0;
        }

        $nomeTokens = QueryNormalizer::tokenizar($nome);

        if (empty($nomeTokens)) {
            return 0.0;
        }

        $matched = 0;

        foreach ($queryTokens as $qToken) {
            foreach ($nomeTokens as $nToken) {
                if (self::tokensEquivalentes($qToken, $nToken)) {
                    $matched++;
                    break;
                }
            }
        }

        return $matched / count($queryTokens);
    }

    /**
     * Returns true when two normalized tokens should be considered equivalent.
     *
     * Checks (in order):
     *   1. Exact match
     *   2. Stem equality (Portuguese plural/singular normalization)
     *   3. Domain synonym expansion (on stems)
     *   4. Fuzzy match via similar_text (only for tokens >= 5 chars)
     */
    private static function tokensEquivalentes(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $aS = self::stem($a);
        $bS = self::stem($b);

        if ($aS === $bS) {
            return true;
        }

        // Domain synonym: check both original and stemmed forms to avoid
        // missing entries whose keys don't survive stemming (e.g. "philips" → "philip")
        $sinonimosA = array_unique(array_merge(
            QueryNormalizer::sinonimos($a),
            QueryNormalizer::sinonimos($aS),
        ));
        if (in_array($b, $sinonimosA, true) || in_array($bS, $sinonimosA, true)) {
            return true;
        }

        $sinonimosB = array_unique(array_merge(
            QueryNormalizer::sinonimos($b),
            QueryNormalizer::sinonimos($bS),
        ));
        if (in_array($a, $sinonimosB, true) || in_array($aS, $sinonimosB, true)) {
            return true;
        }

        // Fuzzy match — only worthwhile for longer tokens to avoid false positives
        if (strlen($a) >= 5 && strlen($b) >= 5) {
            similar_text($a, $b, $percent);
            if ($percent >= 85.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minimal Portuguese stemmer: normalizes common plural forms to their singular.
     *
     * Rules applied (in order, first match wins):
     *   -ais → -al  (universais → universal, radiais → radial)
     *   -eis → -el  (pasteis → pastel)
     *   -oes → -ao  (tornoes → tornao — already de-accented upstream)
     *   -s   → ""   (alicates → alicate, chaves → chave)
     */
    private static function stem(string $token): string
    {
        if (strlen($token) > 4 && str_ends_with($token, 'ais')) {
            return substr($token, 0, -3) . 'al';
        }

        if (strlen($token) > 4 && str_ends_with($token, 'eis')) {
            return substr($token, 0, -3) . 'el';
        }

        if (strlen($token) > 4 && str_ends_with($token, 'oes')) {
            return substr($token, 0, -3) . 'ao';
        }

        if (strlen($token) > 3 && str_ends_with($token, 's')) {
            return substr($token, 0, -1);
        }

        return $token;
    }
}
