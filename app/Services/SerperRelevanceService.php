<?php

namespace App\Services;

use App\Services\Scrapers\QueryNormalizer;

class SerperRelevanceService
{
    private const SCORE_MINIMO = 0.65;

    private const STOPWORDS_MARCA = [
        'tinta', 'torneira', 'registro', 'lampada', 'filtro', 'oleo', 'motor',
        'chave', 'alicate', 'jogo', 'kit', 'produto', 'linha', 'refil',
        'parede', 'cozinha', 'banheiro', 'mesa', 'piso', 'casa', 'uso',
        'amarelo', 'azul', 'verde', 'vermelho', 'branco', 'preto', 'cinza',
        'cromado', 'inox', 'aco', 'metal', 'plastico', 'pvc', 'madeira',
    ];

    private const CORES_MATERIAIS = [
        'amarelo', 'amarela', 'azul', 'verde', 'vermelho', 'vermelha',
        'branco', 'branca', 'preto', 'preta', 'cinza', 'grafite', 'bege',
        'marrom', 'cromado', 'cromada', 'inox', 'aco', 'aluminio', 'metal',
        'plastico', 'pvc', 'madeira', 'fosco', 'fosca', 'brilhante',
    ];

    /**
     * @param  array<string, mixed>  $produto
     * @return array<string, mixed>
     */
    public function aplicar(string $busca, array $produto): array
    {
        $titulo = (string) ($produto['nome'] ?? $produto['titulo'] ?? '');
        $descricao = (string) ($produto['descricao'] ?? '');
        $texto = trim($titulo.' '.$descricao);

        $tokensBusca = $this->tokensRelevantes($busca);
        $tokensTitulo = $this->tokensRelevantes($titulo);
        $tokensTexto = $this->tokensRelevantes($texto);

        $scoreTitulo = $this->scoreTokens($tokensBusca, $tokensTitulo);
        $scoreTexto = $this->scoreTokens($tokensBusca, $tokensTexto);
        $score = ($scoreTitulo * 0.70) + ($scoreTexto * 0.30);

        $caps = [];

        $marcasBusca = $this->marcasDaBusca($busca);
        if (! empty($marcasBusca)) {
            $marcaOk = $this->algumTermoPresente($marcasBusca, $texto);
            $score += $marcaOk ? 0.15 : 0.0;

            if (! $marcaOk) {
                $caps[] = 0.55;
            }
        }

        $especificosBusca = $this->extrairEspecificos($busca);
        $especificosTexto = $this->extrairEspecificos($texto);

        if (! empty($especificosBusca['codigos'])) {
            $codigoOk = $this->algumCodigoPresente($especificosBusca['codigos'], $texto);
            $score = $codigoOk ? max($score, 0.98) : $score;

            if (! $codigoOk) {
                $caps[] = 0.60;
            }
        }

        if (! empty($especificosBusca['medidas'])) {
            $medidaScore = $this->scoreConjunto($especificosBusca['medidas'], $especificosTexto['medidas']);
            $score += $medidaScore * 0.20;

            if ($medidaScore < 1.0) {
                $caps[] = $medidaScore > 0 ? 0.68 : 0.55;
            }
        }

        if (! empty($especificosBusca['cores_materiais'])) {
            $corMaterialScore = $this->scoreConjunto($especificosBusca['cores_materiais'], $especificosTexto['cores_materiais']);
            $score += $corMaterialScore * 0.10;

            if ($corMaterialScore < 1.0) {
                $caps[] = $corMaterialScore > 0 ? 0.69 : 0.62;
            }
        }

        if ($this->buscaNaoPedeKit($busca) && $this->produtoPareceKit($titulo)) {
            $caps[] = 0.64;
        }

        $score = min(1.0, $score);

        if (! empty($caps)) {
            $score = min($score, min($caps));
        }

        $produto['score_serper'] = round($score, 2);
        $produto['score_produto'] = round(max((float) ($produto['score_produto'] ?? 0), $score), 2);
        $produto['correspondencia_fraca'] = $produto['score_produto'] < self::SCORE_MINIMO;
        $produto['atributos_extraidos'] = array_filter(array_merge(
            $produto['atributos_extraidos'] ?? [],
            [
                'score_serper' => $produto['score_serper'],
                'marcas_busca_serper' => $marcasBusca,
                'especificos_busca_serper' => array_filter($especificosBusca),
            ],
        ));

        return $produto;
    }

    /**
     * @return string[]
     */
    private function marcasDaBusca(string $busca): array
    {
        $marcasConhecidas = ProductEnrichmentService::detectarMarcas($busca);

        if (! empty($marcasConhecidas)) {
            return array_map(
                fn (string $marca): string => ProductEnrichmentService::normalizarTexto($marca),
                $marcasConhecidas,
            );
        }

        preg_match_all('/\b[\p{Lu}][\p{L}0-9.+-]{2,}\b/u', $busca, $matches, PREG_OFFSET_CAPTURE);
        $marcas = [];

        foreach ($matches[0] ?? [] as [$palavra, $offset]) {
            $normalizada = ProductEnrichmentService::normalizarTexto($palavra);

            if ($offset === 0 || in_array($normalizada, self::STOPWORDS_MARCA, true)) {
                continue;
            }

            $marcas[] = $normalizada;
        }

        return array_values(array_unique($marcas));
    }

    /**
     * @return array{codigos: string[], medidas: string[], cores_materiais: string[]}
     */
    private function extrairEspecificos(string $texto): array
    {
        $normalizado = mb_strtolower($texto, 'UTF-8');
        $normalizado = QueryNormalizer::removerAcentos($normalizado);
        $normalizado = str_replace(',', '.', $normalizado);
        $medidas = [];

        preg_match_all('/\b(\d+(?:\.\d+)?)\s*(ml|l|litros?|kg|g|mm|cm|m|pol|polegadas?|v|w|kw)\b/u', $normalizado, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $medidas[] = $this->normalizarMedida($match[1], $match[2]);
        }

        $codigos = [];
        preg_match_all('/\b(?=[a-z0-9.-]*\d)(?=[a-z0-9.-]*[a-z])[a-z0-9]+(?:[.-][a-z0-9]+)*\b/u', $normalizado, $codigoMatches);
        foreach ($codigoMatches[0] ?? [] as $codigo) {
            $limpo = preg_replace('/[^a-z0-9]/', '', $codigo) ?? '';

            if (strlen($limpo) >= 4 && ! $this->pareceMedida($codigo)) {
                $codigos[] = $limpo;
            }
        }

        $tokens = QueryNormalizer::tokenizar($texto);

        return [
            'codigos' => array_values(array_unique($codigos)),
            'medidas' => array_values(array_unique(array_filter($medidas))),
            'cores_materiais' => array_values(array_unique(array_intersect($tokens, self::CORES_MATERIAIS))),
        ];
    }

    private function normalizarMedida(string $valor, string $unidade): string
    {
        $numero = (float) str_replace(',', '.', $valor);
        $unidade = rtrim($unidade, 's');

        return match ($unidade) {
            'l', 'litro' => ((int) round($numero * 1000)).'ml',
            'kg' => ((int) round($numero * 1000)).'g',
            'polegada' => $numero.'pol',
            default => rtrim(rtrim(number_format($numero, 3, '.', ''), '0'), '.').$unidade,
        };
    }

    private function pareceMedida(string $codigo): bool
    {
        return preg_match('/^\d+(?:[,.]\d+)?\s*(?:ml|l|kg|g|mm|cm|m|pol|v|w|kw)$/i', $codigo) === 1;
    }

    /**
     * @return string[]
     */
    private function tokensRelevantes(string $texto): array
    {
        return array_values(array_filter(
            QueryNormalizer::tokenizar($texto),
            fn (string $token): bool => ! in_array($token, ['de', 'da', 'do', 'com', 'para', 'em', 'por'], true),
        ));
    }

    /**
     * @param  string[]  $tokensBusca
     * @param  string[]  $tokensProduto
     */
    private function scoreTokens(array $tokensBusca, array $tokensProduto): float
    {
        if (empty($tokensBusca) || empty($tokensProduto)) {
            return 0.0;
        }

        $matches = 0;

        foreach ($tokensBusca as $tokenBusca) {
            foreach ($tokensProduto as $tokenProduto) {
                if ($this->tokensEquivalentes($tokenBusca, $tokenProduto)) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / count($tokensBusca);
    }

    private function tokensEquivalentes(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (strlen($a) >= 4 && strlen($b) >= 4 && (str_contains($a, $b) || str_contains($b, $a))) {
            return true;
        }

        if (strlen($a) >= 5 && strlen($b) >= 5) {
            similar_text($a, $b, $percent);

            return $percent >= 88.0;
        }

        return false;
    }

    /**
     * @param  string[]  $esperados
     * @param  string[]  $encontrados
     */
    private function scoreConjunto(array $esperados, array $encontrados): float
    {
        if (empty($esperados)) {
            return 1.0;
        }

        $matches = 0;

        foreach ($esperados as $esperado) {
            foreach ($encontrados as $encontrado) {
                if ($this->tokensEquivalentes($esperado, $encontrado)) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / count($esperados);
    }

    /**
     * @param  string[]  $termos
     */
    private function algumTermoPresente(array $termos, string $texto): bool
    {
        $normalizado = ' '.ProductEnrichmentService::normalizarTexto($texto).' ';

        foreach ($termos as $termo) {
            if (preg_match('/\b'.preg_quote($termo, '/').'\b/', $normalizado) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $codigos
     */
    private function algumCodigoPresente(array $codigos, string $texto): bool
    {
        $normalizado = preg_replace('/[^a-z0-9]/', '', ProductEnrichmentService::normalizarTexto($texto)) ?? '';

        foreach ($codigos as $codigo) {
            if ($codigo !== '' && str_contains($normalizado, $codigo)) {
                return true;
            }
        }

        return false;
    }

    private function buscaNaoPedeKit(string $busca): bool
    {
        return ! $this->algumTermoPresente(['kit', 'jogo', 'conjunto'], $busca);
    }

    private function produtoPareceKit(string $titulo): bool
    {
        return $this->algumTermoPresente(['kit', 'jogo', 'conjunto', 'combo'], $titulo);
    }
}
