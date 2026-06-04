<?php

namespace App\Services\Scrapers;

/**
 * Normalizes search terms for consistent query building and relevance comparison.
 */
class QueryNormalizer
{
    /** Words ignored during tokenization (too generic to be meaningful). */
    private const STOPWORDS = [
        'de', 'da', 'do', 'das', 'dos', 'com', 'para', 'e', 'em',
        'a', 'o', 'um', 'uma', 'por', 'no', 'na', 'nos', 'nas', 'ou',
    ];

    /**
     * Known synonym groups in the tools domain.
     * Each group lists terms that are interchangeable for relevance matching.
     * Keys are canonical forms; values are alternative tokens.
     *
     * @var array<string, string[]>
     */
    private const SINONIMOS = [
        'philips'        => ['cruz', 'estrela'],
        'fenda'          => ['plana', 'chata'],
        'esmerilhadeira' => ['esmeril', 'rebarbadora'],
        'parafusadeira'  => ['parafusador'],
        'furadeira'      => ['drill'],
        'vise'           => ['pressao', 'grip'],
        'chave'          => ['chave'],
        'alicate'        => ['alicate'],
    ];

    /**
     * Cleans the raw search term for display and site queries:
     * trims whitespace, collapses multiple spaces, preserves accents.
     */
    public static function limpar(string $termo): string
    {
        return preg_replace('/\s{2,}/', ' ', trim($termo));
    }

    /**
     * Splits the term into normalized, meaningful tokens:
     * lowercased, accent-stripped, stopwords removed, deduplicated.
     *
     * @return string[]
     */
    public static function tokenizar(string $termo): array
    {
        $normalizado = mb_strtolower($termo, 'UTF-8');
        $normalizado = self::removerAcentos($normalizado);
        $normalizado = str_replace(['”', '″'], '"', $normalizado);
        $normalizado = preg_replace('/\b(\d+)[,.](\d+\/\d+)\b/', '$1 $2', $normalizado) ?? $normalizado;
        $normalizado = preg_replace(
            '/\b(\d+(?:[,.]\d+)?)\s*(mm|cm|m|kg|g|pol|polegadas?|v|w|hp|cv)\s*(?:a|-)\s*(\d+(?:[,.]\d+)?)\s*(mm|cm|m|kg|g|pol|polegadas?|v|w|hp|cv)\b/u',
            '$1$2 $3$4',
            $normalizado,
        ) ?? $normalizado;
        $normalizado = preg_replace(
            '/\b(\d+(?:[,.]\d+)?)\s*(?:a|-)\s*(\d+(?:[,.]\d+)?)\s*(mm|cm|m|kg|g|pol|polegadas?|v|w|hp|cv)\b/u',
            '$1$3 $2$3',
            $normalizado,
        ) ?? $normalizado;

        preg_match_all(
            '/\b\d+\/\d+\b|\b\d+(?:[,.]\d+)?\s*(?:"|(?:mm|cm|m|kg|g|pol|polegadas?|v|w|hp|cv)\b)|\b(?=[a-z0-9.-]*\d)[a-z0-9]+(?:[.-][a-z0-9]+)*\b|\b[a-z]+\b/u',
            $normalizado,
            $matches,
        );

        $tokens = array_filter(
            array_map(fn (string $token): string => self::normalizarToken($token), $matches[0] ?? []),
            fn (string $t) => strlen($t) >= 2 && !in_array($t, self::STOPWORDS, true),
        );

        return array_values(array_unique($tokens));
    }

    /**
     * Returns all synonym tokens for a given token, including the token itself.
     * Used by the relevance filter to broaden matching.
     *
     * @return string[]
     */
    public static function sinonimos(string $token): array
    {
        $token = self::removerAcentos(mb_strtolower($token, 'UTF-8'));

        $grupo = self::SINONIMOS[$token] ?? [];

        // Also check if this token appears as a synonym value in any group
        foreach (self::SINONIMOS as $canonical => $alternativas) {
            if (in_array($token, $alternativas, true)) {
                $grupo = array_merge($grupo, [$canonical], $alternativas);
                break;
            }
        }

        return array_values(array_unique(array_merge([$token], $grupo)));
    }

    /**
     * Strips accented characters, replacing them with their ASCII equivalents.
     */
    public static function removerAcentos(string $str): string
    {
        static $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i',
            'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
            'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
            'Ç' => 'c', 'Ñ' => 'n',
        ];

        return strtr($str, $map);
    }

    private static function normalizarToken(string $token): string
    {
        $token = trim($token);
        $token = preg_replace('/\s+/', '', $token) ?? $token;

        if (str_contains($token, '"')) {
            $token = str_replace('"', 'pol', $token);
        }

        if (preg_match('/^\d+(?:[,.]\d+)?(?:mm|cm|m|kg|g|pol|polegadas?|v|w|hp|cv)$/', $token) === 1) {
            return str_replace(',', '.', preg_replace('/polegadas?$/', 'pol', $token) ?? $token);
        }

        if (str_contains($token, '/')) {
            return $token;
        }

        return preg_replace('/[^a-z0-9]/', '', $token) ?? '';
    }
}
