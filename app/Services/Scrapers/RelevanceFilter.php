<?php

namespace App\Services\Scrapers;

/**
 * Filters and ranks scraper results by relevance to the original search term.
 *
 * Scoring strategy — final score = max of:
 *   - nome_score    (full weight)   — fraction of query tokens found in the product title
 *   - codigo_score  (full weight)   — applied only when the query contains a code/reference
 *   - desc_score    (0.75 weight)   — same, applied to the product description
 *
 * Token matching considers:
 *   - Exact match after normalization
 *   - Plural/singular variation via a minimal Portuguese stemmer
 *   - Known domain synonyms (via QueryNormalizer::sinonimos)
 *   - Fuzzy similarity for longer words (similar_text >= 85%)
 *
 * Results with final score < THRESHOLD are discarded; survivors are sorted by score descending.
 */
class RelevanceFilter
{
    /** Minimum score for a result to be included. */
    private const THRESHOLD = 0.5;

    /** Weight applied to description scores (lower than title/code to avoid noise). */
    private const DESC_WEIGHT = 0.75;

    /**
     * Kit/conjunto words: if the query does NOT contain any of these, results
     * whose name contains them are discarded (user searched a single tool, not a set).
     */
    private const PALAVRAS_KIT = ['jogo', 'kit', 'conjunto', 'berco', 'maleta', 'suporte', 'porta'];

    /**
     * Filters $resultados to those relevant to $termo, sorted by score descending.
     * If the term produces no tokens (too short / all stopwords), returns as-is.
     *
     * Two passes:
     *   1. Relevance score — discards results below THRESHOLD
     *   2. Kit filter     — discards kits/sets when the query is for a single tool
     *
     * @param  array<array{nome: string, descricao: string|null, codigo: string|null, ...}> $resultados
     * @return array<array{nome: string, descricao: string|null, codigo: string|null, ...}>
     */
    public static function filtrar(array $resultados, string $termo): array
    {
        $queryTokens = QueryNormalizer::tokenizar($termo);

        if (empty($queryTokens)) {
            return $resultados;
        }

        $queryTemKit = !empty(array_intersect($queryTokens, self::PALAVRAS_KIT));

        $pontuados = [];

        foreach ($resultados as $resultado) {
            $score = self::pontuar($resultado, $queryTokens);

            if ($score < self::THRESHOLD) {
                continue;
            }

            // Discard kits/sets when the user did not ask for one
            if (!$queryTemKit && self::eKit($resultado['nome'] ?? '')) {
                continue;
            }

            $pontuados[] = ['r' => $resultado, 's' => $score];
        }

        usort($pontuados, fn ($a, $b) => $b['s'] <=> $a['s']);

        return array_column($pontuados, 'r');
    }

    /**
     * Returns a relevance score [0.0, 1.0] for a result array against the query tokens.
     *
     * Scores each available field independently and returns the highest, so a
     * description match can surface a result even when the title alone would not.
     * Product codes are scored only if the user searched a code/reference.
     *
     * @param  array{nome?: string, descricao?: string|null, codigo?: string|null} $resultado
     * @param  string[] $queryTokens
     */
    public static function pontuar(array $resultado, array $queryTokens): float
    {
        if (empty($queryTokens)) {
            return 0.0;
        }

        $scores = [];

        if (!empty($resultado['nome'])) {
            $scores[] = self::pontuarTexto($resultado['nome'], $queryTokens);
        }

        if (!empty($resultado['codigo']) && self::queryContemCodigo($queryTokens)) {
            $scores[] = self::pontuarTexto($resultado['codigo'], $queryTokens);
        }

        if (!empty($resultado['descricao'])) {
            $scores[] = self::pontuarTexto($resultado['descricao'], $queryTokens) * self::DESC_WEIGHT;
        }

        return empty($scores) ? 0.0 : max($scores);
    }

    /**
     * @param string[] $queryTokens
     */
    private static function queryContemCodigo(array $queryTokens): bool
    {
        foreach ($queryTokens as $token) {
            if (self::pareceCodigoBuscado($token)) {
                return true;
            }
        }

        return false;
    }

    private static function pareceCodigoBuscado(string $token): bool
    {
        if (preg_match('/^\d+(?:[,.]\d+)?(?:mm|cm|m|kg|g|pol|polegadas?|v|w|hp|cv)$/', $token) === 1) {
            return false;
        }

        if (str_contains($token, '/')) {
            return false;
        }

        if (ctype_digit($token)) {
            return strlen($token) >= 5;
        }

        return strlen($token) >= 4
            && preg_match('/[a-z]/', $token) === 1
            && preg_match('/\d/', $token) === 1;
    }

    /**
     * Scores a single text field: fraction of query tokens found in the field tokens.
     *
     * @param string[] $queryTokens
     */
    private static function pontuarTexto(string $texto, array $queryTokens): float
    {
        $tokens = QueryNormalizer::tokenizar($texto);

        if (empty($tokens)) {
            return 0.0;
        }

        $matched = 0;

        foreach ($queryTokens as $qToken) {
            foreach ($tokens as $tToken) {
                if (self::tokensEquivalentes($qToken, $tToken)) {
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

        if ((strlen($a) >= 5 || strlen($b) >= 5) && (str_contains($a, $b) || str_contains($b, $a))) {
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
     * Returns true if the product name contains a kit/set word,
     * indicating it is a bundle rather than a single tool.
     */
    private static function eKit(string $nome): bool
    {
        $tokens = QueryNormalizer::tokenizar($nome);
        return !empty(array_intersect($tokens, self::PALAVRAS_KIT));
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
