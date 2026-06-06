<?php

namespace App\Services;

use App\Services\Scrapers\QueryNormalizer;

class ProductEnrichmentService
{
    private const LIMITE_CORRESPONDENCIA_FRACA = 0.50;

    /** @var string[] */
    private const MARCAS = [
        'Tramontina',
        'Bosch',
        'Makita',
        'Dewalt',
        'Vonder',
        'Stanley',
        'Worker',
        'Black+Decker',
        'Einhell',
        'Schulz',
        'Gedore RED',
        'Gedore',
        'Robust',
        'Raven',
        'Tenace',
        'SATA',
        'MTX',
        'Rocast',
        'Bremen',
        'Motomil',
        'Fercar',
        'Ferrar',
        'Marcon',
        'Sparta',
        'EDA',
        'Brasfort',
        'Belzer',
        'Irwin',
        'Starrett',
    ];

    /** @var array<string, array{categoria: string, tipo: string}> */
    private const TIPOS = [
        'esmerilhadeira angular' => ['categoria' => 'ferramenta eletrica', 'tipo' => 'esmerilhadeira angular'],
        'furadeira de impacto' => ['categoria' => 'ferramenta eletrica', 'tipo' => 'furadeira de impacto'],
        'parafusadeira' => ['categoria' => 'ferramenta eletrica', 'tipo' => 'parafusadeira'],
        'furadeira' => ['categoria' => 'ferramenta eletrica', 'tipo' => 'furadeira'],
        'esmerilhadeira' => ['categoria' => 'ferramenta eletrica', 'tipo' => 'esmerilhadeira'],
        'alicate para aneis externo' => ['categoria' => 'ferramenta manual', 'tipo' => 'alicate para aneis externo'],
        'alicate para aneis internos' => ['categoria' => 'ferramenta manual', 'tipo' => 'alicate para aneis interno'],
        'alicate para aneis interno' => ['categoria' => 'ferramenta manual', 'tipo' => 'alicate para aneis interno'],
        'alicate de pressao' => ['categoria' => 'ferramenta manual', 'tipo' => 'alicate de pressao'],
        'alicate universal' => ['categoria' => 'ferramenta manual', 'tipo' => 'alicate universal'],
        'alicate corte' => ['categoria' => 'ferramenta manual', 'tipo' => 'alicate corte'],
        'alicate para abracadeira' => ['categoria' => 'ferramenta manual', 'tipo' => 'alicate para abracadeira'],
        'jogo de soquete' => ['categoria' => 'ferramenta manual', 'tipo' => 'jogo de soquete'],
        'jogo chave combinada' => ['categoria' => 'ferramenta manual', 'tipo' => 'jogo de chaves combinadas'],
        'jogo de chave combinada' => ['categoria' => 'ferramenta manual', 'tipo' => 'jogo de chaves combinadas'],
        'jogo chave fixa' => ['categoria' => 'ferramenta manual', 'tipo' => 'jogo de chaves fixas'],
        'jogo chave torx' => ['categoria' => 'ferramenta manual', 'tipo' => 'jogo de chaves torx'],
        'jogo chave estrela' => ['categoria' => 'ferramenta manual', 'tipo' => 'jogo de chaves estrela'],
        'jogo chave allen' => ['categoria' => 'ferramenta manual', 'tipo' => 'jogo de chaves allen'],
        'jogo de chave allen' => ['categoria' => 'ferramenta manual', 'tipo' => 'jogo de chaves allen'],
        'jogo de chaves' => ['categoria' => 'ferramenta manual', 'tipo' => 'jogo de chaves'],
        'chave canhao' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave canhao'],
        'chave phillips' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave philips'],
        'chave philips' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave philips'],
        'chave combinada' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave combinada'],
        'chave de impacto' => ['categoria' => 'ferramenta eletrica', 'tipo' => 'chave de impacto'],
        'chave para tubos' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave grifo'],
        'chave para tubo' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave grifo'],
        'chave tubo' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave grifo'],
        'chave stilson' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave grifo'],
        'chave de fenda' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave fenda'],
        'chave grifo' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave grifo'],
        'chave griffo' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave grifo'],
        'chave americana' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave grifo'],
        'stilson' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave grifo'],
        'grifo' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave grifo'],
        'chave fenda' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave fenda'],
        'chave inglesa' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave inglesa'],
        'chave estrela' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave estrela'],
        'chave fixa' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave fixa'],
        'chave biela' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave biela'],
        'chave' => ['categoria' => 'ferramenta manual', 'tipo' => 'chave'],
        'cabo t' => ['categoria' => 'ferramenta manual', 'tipo' => 'cabo t'],
        'martelo unha' => ['categoria' => 'ferramenta manual', 'tipo' => 'martelo unha'],
        'martelo' => ['categoria' => 'ferramenta manual', 'tipo' => 'martelo'],
        'marreta' => ['categoria' => 'ferramenta manual', 'tipo' => 'marreta'],
        'alicate' => ['categoria' => 'ferramenta manual', 'tipo' => 'alicate'],
        'arco p serra' => ['categoria' => 'ferramenta manual', 'tipo' => 'arco de serra'],
        'arco serra' => ['categoria' => 'ferramenta manual', 'tipo' => 'arco de serra'],
        'arco para serra' => ['categoria' => 'ferramenta manual', 'tipo' => 'arco de serra'],
        'serra' => ['categoria' => 'ferramenta eletrica', 'tipo' => 'serra'],
        'lixadeira' => ['categoria' => 'ferramenta eletrica', 'tipo' => 'lixadeira'],
        'compressor' => ['categoria' => 'maquina', 'tipo' => 'compressor'],
        'carrinho mecanico' => ['categoria' => 'ferramenta manual', 'tipo' => 'carrinho mecanico'],
        'caixa de ferramentas' => ['categoria' => 'ferramenta manual', 'tipo' => 'caixa de ferramentas'],
    ];

    /** @var string[] */
    private const STOPWORDS = [
        'de', 'da', 'do', 'das', 'dos', 'com', 'para', 'e', 'em',
        'a', 'o', 'um', 'uma', 'por', 'no', 'na', 'nos', 'nas', 'ou',
        'cabo', 'peca', 'pecas',
    ];

    /**
     * Remove acentos, baixa caixa, troca caracteres especiais por espaco
     * e colapsa espacos duplicados.
     */
    public static function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = self::removerAcentos($texto);
        $texto = str_replace('+', ' ', $texto);
        $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto) ?? '';
        $texto = preg_replace('/\s+/', ' ', $texto) ?? '';

        return trim($texto);
    }

    /**
     * @return string[]
     */
    public static function marcasConhecidas(): array
    {
        return self::MARCAS;
    }

    /**
     * @return array{marca_detectada: string|null, score_confianca: float}
     */
    public static function detectarMarca(string $titulo): array
    {
        $marcas = self::detectarMarcas($titulo);

        if (! empty($marcas)) {
            return [
                'marca_detectada' => $marcas[0],
                'score_confianca' => 1.0,
            ];
        }

        return [
            'marca_detectada' => null,
            'score_confianca' => 0.0,
        ];
    }

    /**
     * @return string[]
     */
    public static function detectarMarcas(string $titulo): array
    {
        $tituloNormalizado = ' '.self::normalizarParaMatch($titulo).' ';
        $candidatas = [];

        foreach (self::marcasOrdenadasParaMatch() as $marca) {
            $marcaNormalizada = self::normalizarParaMatch($marca);

            if (preg_match_all('/\b'.preg_quote($marcaNormalizada, '/').'\b/', $tituloNormalizado, $matches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }

            foreach ($matches[0] as [$texto, $inicio]) {
                $candidatas[] = [
                    'marca' => $marca,
                    'inicio' => $inicio,
                    'fim' => $inicio + strlen($texto),
                ];
            }
        }

        usort($candidatas, function (array $a, array $b): int {
            $tamanhoA = $a['fim'] - $a['inicio'];
            $tamanhoB = $b['fim'] - $b['inicio'];

            return ($tamanhoB <=> $tamanhoA) ?: ($a['inicio'] <=> $b['inicio']);
        });

        $encontradas = [];
        $marcasEncontradas = [];

        foreach ($candidatas as $candidata) {
            if (isset($marcasEncontradas[$candidata['marca']])) {
                continue;
            }

            foreach ($encontradas as $encontrada) {
                if ($candidata['inicio'] >= $encontrada['inicio'] && $candidata['fim'] <= $encontrada['fim']) {
                    continue 2;
                }
            }

            $encontradas[] = $candidata;
            $marcasEncontradas[$candidata['marca']] = true;
        }

        return array_column($encontradas, 'marca');
    }

    /**
     * @return string[]
     */
    public static function termosBuscaPorMarca(string $termo): array
    {
        $marcas = self::detectarMarcas($termo);
        $codigos = self::extrairCodigos($termo);

        if (count($marcas) <= 1 && empty($codigos)) {
            return [self::normalizarTermoParaLoja($termo)];
        }

        $marcasDetectadas = array_flip($marcas);
        $baseComCodigos = $termo;

        foreach (self::marcasOrdenadasParaMatch() as $marca) {
            if (! isset($marcasDetectadas[$marca])) {
                continue;
            }

            $baseComCodigos = preg_replace('/\b'.preg_quote($marca, '/').'\b/i', ' ', $baseComCodigos) ?? $baseComCodigos;
        }

        $baseSemCodigos = self::removerCodigosDoTermo($baseComCodigos, $codigos);
        $marcasParaBuscar = empty($marcas) ? [null] : $marcas;
        $termos = [];

        foreach ($marcasParaBuscar as $marca) {
            $termos[] = self::normalizarTermoParaLoja(trim($baseComCodigos.' '.($marca ?? '')));

            if ($baseSemCodigos !== $baseComCodigos) {
                $termos[] = self::normalizarTermoParaLoja(trim($baseSemCodigos.' '.($marca ?? '')));
            }

            foreach ($codigos as $codigo) {
                $termos[] = self::normalizarTermoParaLoja(trim(($marca ?? '').' '.$codigo));
                $termos[] = self::normalizarTermoParaLoja($codigo);
            }
        }

        return self::valoresUnicosNaoVazios($termos);
    }

    /**
     * Retorna apenas códigos/referências que vieram da busca do usuário.
     *
     * @return string[]
     */
    public static function extrairCodigosDaBusca(string $termo): array
    {
        return self::extrairCodigos($termo);
    }

    /**
     * @return array{
     *     categoria: string|null,
     *     tipo: string|null,
     *     modelo: string|null,
     *     potencia: string|null,
     *     voltagem: string|null,
     *     medida: string|null,
     *     peso: string|null,
     *     material: string|null
     * }
     */
    public static function extrairAtributos(string $titulo): array
    {
        $normalizado = self::normalizarTexto($titulo);
        $atributos = [
            'categoria' => null,
            'tipo' => null,
            'modelo' => null,
            'potencia' => self::extrairPrimeiro($titulo, '/\b\d+(?:[,.]\d+)?\s*(?:w|kw|hp|cv)\b/i'),
            'voltagem' => self::extrairPrimeiro($titulo, '/\b(?:bivolt|\d+(?:[,.]\d+)?\s*v)\b/i'),
            'medida' => self::extrairMedida($titulo),
            'peso' => self::extrairPrimeiro($titulo, '/\b\d+(?:[,.]\d+)?\s*(?:kg|g)\b/i'),
            'material' => self::extrairMaterial($normalizado),
        ];

        foreach (self::TIPOS as $tipo => $dados) {
            if (self::contemTermo($normalizado, $tipo)) {
                $atributos['categoria'] = $dados['categoria'];
                $atributos['tipo'] = $dados['tipo'];
                break;
            }
        }

        if (
            ($atributos['tipo'] === null || $atributos['tipo'] === 'chave')
            && preg_match('/\bchave\b.*\bcombinada\b/', $normalizado) === 1
        ) {
            $atributos['categoria'] = 'ferramenta manual';
            $atributos['tipo'] = 'chave combinada';
        }

        $atributos['modelo'] = self::extrairModelo($titulo);

        return $atributos;
    }

    /**
     * @param  array<string, mixed>  $produto
     */
    public static function calcularScoreProduto(string $buscaUsuario, array $produto): float
    {
        $titulo = (string) ($produto['nome'] ?? $produto['titulo'] ?? '');
        $atributos = self::atributosDoProduto($produto, $titulo);
        $atributosBusca = self::extrairAtributos($buscaUsuario);
        $marca = (string) ($produto['marca_detectada'] ?? self::detectarMarca($titulo)['marca_detectada'] ?? '');
        $marcasBusca = self::detectarMarcas($buscaUsuario);
        $marcaBuscaCompativel = $marca === '' ? null : self::marcaCompativelNaLista($marca, $marcasBusca);
        $codigoProduto = (string) ($produto['codigo'] ?? '');
        $scoreCodigo = self::scoreCodigo(self::extrairCodigos($buscaUsuario), trim($titulo.' '.$codigoProduto));

        if (! empty($marcasBusca) && $marcaBuscaCompativel === null) {
            return 0.0;
        }

        if (self::tiposIncompativeis($atributosBusca['tipo'], $atributos['tipo'] ?? null)) {
            return 0.0;
        }

        $tokensBusca = self::tokenizarBuscaParaProduto($buscaUsuario, $marcasBusca, $marcaBuscaCompativel);
        if (empty($tokensBusca)) {
            return 0.0;
        }

        if (! self::medidasCompativeis($tokensBusca, self::tokenizar($titulo.' '.implode(' ', array_filter($atributos))))) {
            return 0.0;
        }

        $scoreTitulo = self::scoreTokens($tokensBusca, self::tokenizar($titulo));
        $scoreMarca = $marcaBuscaCompativel !== null ? 1.0 : self::scoreCampo($buscaUsuario, $marca);
        $scoreCategoria = self::scoreCampo($buscaUsuario, (string) ($atributos['categoria'] ?? ''));
        $scoreTipo = self::scoreCampo($buscaUsuario, (string) ($atributos['tipo'] ?? ''));
        $scoreAtributos = self::scoreTokens($tokensBusca, self::tokenizar(implode(' ', array_filter([
            $atributos['modelo'] ?? null,
            $atributos['potencia'] ?? null,
            $atributos['voltagem'] ?? null,
            $atributos['medida'] ?? null,
            $atributos['peso'] ?? null,
            $atributos['material'] ?? null,
        ]))));

        if (empty($marcasBusca)) {
            $score = ($scoreTitulo * 0.65)
                + ($scoreCategoria * 0.05)
                + ($scoreTipo * 0.20)
                + ($scoreAtributos * 0.10);
        } else {
            $score = ($scoreTitulo * 0.45)
                + ($scoreMarca * 0.20)
                + ($scoreCategoria * 0.10)
                + ($scoreTipo * 0.15)
                + ($scoreAtributos * 0.10);
        }

        if ($scoreCodigo > 0.0) {
            $score = max($score, $scoreCodigo);
        }

        return round(min(1.0, $score), 2);
    }

    /**
     * @param  array<string, mixed>  $produto
     * @return array<string, mixed>
     */
    public static function enriquecerProduto(array $produto, ?string $buscaUsuario = null): array
    {
        $titulo = (string) ($produto['nome'] ?? $produto['titulo'] ?? '');
        $marca = self::detectarMarca($titulo);
        $atributos = self::extrairAtributos($titulo);

        $produto['marca_detectada'] = $marca['marca_detectada'];
        $produto['score_confianca_marca'] = $marca['score_confianca'];
        $produto['atributos_extraidos'] = $atributos;

        if ($buscaUsuario !== null) {
            $score = self::calcularScoreProduto($buscaUsuario, $produto);
            $produto['score_produto'] = $score;
            $produto['correspondencia_fraca'] = $score < self::LIMITE_CORRESPONDENCIA_FRACA;
        }

        return $produto;
    }

    /**
     * @param  array<int, array<string, mixed>>  $produtos
     * @return array<int, array<string, mixed>>
     */
    public static function enriquecerProdutos(array $produtos, ?string $buscaUsuario = null): array
    {
        return array_map(
            fn (array $produto) => self::enriquecerProduto($produto, $buscaUsuario),
            $produtos,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $produtos
     * @return array<int, array<string, mixed>>
     */
    public static function filtrarProdutosConfiaveis(array $produtos): array
    {
        return array_values(array_filter(
            $produtos,
            fn (array $produto) => ($produto['correspondencia_fraca'] ?? false) === false,
        ));
    }

    private static function normalizarParaMatch(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = self::removerAcentos($texto);
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '';
        $texto = preg_replace('/\s+/', ' ', $texto) ?? '';

        return trim($texto);
    }

    private static function normalizarCodigo(string $codigo): string
    {
        return preg_replace('/[^a-z0-9]/', '', self::normalizarParaMatch($codigo)) ?? '';
    }

    /**
     * @return string[]
     */
    private static function marcasOrdenadasParaMatch(): array
    {
        $marcas = array_map(
            fn (string $marca, int $indice): array => ['marca' => $marca, 'indice' => $indice],
            self::MARCAS,
            array_keys(self::MARCAS),
        );

        usort($marcas, function (array $a, array $b): int {
            return (strlen($b['marca']) <=> strlen($a['marca'])) ?: ($a['indice'] <=> $b['indice']);
        });

        return array_column($marcas, 'marca');
    }

    /**
     * @return string[]
     */
    private static function tokenizar(string $texto): array
    {
        $tokens = array_filter(
            QueryNormalizer::tokenizar($texto),
            fn (string $token) => strlen($token) >= 2 && ! in_array($token, self::STOPWORDS, true),
        );

        return array_values(array_unique($tokens));
    }

    /**
     * @param  string[]  $marcasBusca
     * @return string[]
     */
    private static function tokenizarBuscaParaProduto(string $buscaUsuario, array $marcasBusca, ?string $marcaBuscaCompativel): array
    {
        $tokensBusca = self::tokenizar($buscaUsuario);

        if (count($marcasBusca) <= 1 || $marcaBuscaCompativel === null) {
            return $tokensBusca;
        }

        $tokensMarcasAlternativas = [];

        foreach ($marcasBusca as $marcaBusca) {
            if (self::mesmaMarca($marcaBusca, $marcaBuscaCompativel)) {
                continue;
            }

            $tokensMarcasAlternativas = array_merge($tokensMarcasAlternativas, self::tokenizar($marcaBusca));
        }

        return array_values(array_filter(
            $tokensBusca,
            fn (string $token) => ! in_array($token, $tokensMarcasAlternativas, true),
        ));
    }

    /**
     * @param  string[]  $tokensBusca
     * @param  string[]  $tokensProduto
     */
    private static function scoreTokens(array $tokensBusca, array $tokensProduto): float
    {
        if (empty($tokensBusca) || empty($tokensProduto)) {
            return 0.0;
        }

        $matches = 0;

        foreach ($tokensBusca as $tokenBusca) {
            foreach ($tokensProduto as $tokenProduto) {
                if (self::tokensEquivalentes($tokenBusca, $tokenProduto)) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / count($tokensBusca);
    }

    /**
     * @param  string[]  $codigosBusca
     */
    private static function scoreCodigo(array $codigosBusca, string $textoProduto): float
    {
        if (empty($codigosBusca)) {
            return 0.0;
        }

        $produtoNormalizado = self::normalizarCodigo($textoProduto);

        foreach ($codigosBusca as $codigoBusca) {
            $codigoNormalizado = self::normalizarCodigo($codigoBusca);

            if ($codigoNormalizado !== '' && str_contains($produtoNormalizado, $codigoNormalizado)) {
                return 1.0;
            }
        }

        return 0.0;
    }

    private static function scoreCampo(string $buscaUsuario, string $valor): float
    {
        if ($valor === '') {
            return 0.0;
        }

        $tokensValor = self::tokenizar($valor);
        if (empty($tokensValor)) {
            return 0.0;
        }

        $tokensBusca = self::tokenizar($buscaUsuario);
        $matches = 0;

        foreach ($tokensValor as $tokenValor) {
            foreach ($tokensBusca as $tokenBusca) {
                if (self::tokensEquivalentes($tokenValor, $tokenBusca)) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / count($tokensValor);
    }

    private static function tokensEquivalentes(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (strlen($a) > 3 && str_ends_with($a, 's')) {
            $a = substr($a, 0, -1);
        }

        if (strlen($b) > 3 && str_ends_with($b, 's')) {
            $b = substr($b, 0, -1);
        }

        if ($a === $b) {
            return true;
        }

        if ((strlen($a) >= 5 || strlen($b) >= 5) && (str_contains($a, $b) || str_contains($b, $a))) {
            return true;
        }

        $sinonimosA = QueryNormalizer::sinonimos($a);
        if (in_array($b, $sinonimosA, true)) {
            return true;
        }

        $sinonimosB = QueryNormalizer::sinonimos($b);
        if (in_array($a, $sinonimosB, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  string[]  $tokensBusca
     * @param  string[]  $tokensProduto
     */
    private static function medidasCompativeis(array $tokensBusca, array $tokensProduto): bool
    {
        $medidasBusca = array_values(array_filter($tokensBusca, fn (string $token): bool => self::ehTokenMedida($token)));

        if (empty($medidasBusca)) {
            return true;
        }

        foreach ($medidasBusca as $medidaBusca) {
            $encontrou = false;

            foreach ($tokensProduto as $tokenProduto) {
                if (self::tokensEquivalentes($medidaBusca, $tokenProduto)) {
                    $encontrou = true;
                    break;
                }
            }

            if (! $encontrou) {
                return false;
            }
        }

        return true;
    }

    private static function ehTokenMedida(string $token): bool
    {
        return preg_match('/^\d+(?:[,.]\d+)?(?:mm|cm|m|kg|g)$/', $token) === 1;
    }

    private static function mesmaMarca(string $a, string $b): bool
    {
        $aNormalizada = self::normalizarParaMatch($a);
        $bNormalizada = self::normalizarParaMatch($b);

        return $aNormalizada === $bNormalizada
            || str_starts_with($aNormalizada, $bNormalizada.' ')
            || str_starts_with($bNormalizada, $aNormalizada.' ');
    }

    /**
     * @param  string[]  $marcasBusca
     */
    private static function marcaCompativelNaLista(string $marcaProduto, array $marcasBusca): ?string
    {
        foreach ($marcasBusca as $marcaBusca) {
            if (self::mesmaMarca($marcaBusca, $marcaProduto)) {
                return $marcaBusca;
            }
        }

        return null;
    }

    private static function tiposIncompativeis(?string $tipoBusca, ?string $tipoProduto): bool
    {
        if ($tipoBusca === null || $tipoProduto === null) {
            return false;
        }

        if ($tipoBusca === $tipoProduto) {
            return false;
        }

        $buscaEhChave = str_starts_with($tipoBusca, 'chave ');
        $produtoEhChave = str_starts_with($tipoProduto, 'chave ');

        if ($buscaEhChave && $tipoProduto === 'chave') {
            return true;
        }

        return $buscaEhChave && $produtoEhChave;
    }

    /**
     * @param  array<string, mixed>  $produto
     * @return array<string, mixed>
     */
    private static function atributosDoProduto(array $produto, string $titulo): array
    {
        $atributos = $produto['atributos_extraidos'] ?? null;

        if (is_array($atributos)) {
            return $atributos;
        }

        return self::extrairAtributos($titulo);
    }

    private static function contemTermo(string $textoNormalizado, string $termo): bool
    {
        return preg_match('/\b'.preg_quote($termo, '/').'\b/', $textoNormalizado) === 1;
    }

    private static function extrairPrimeiro(string $titulo, string $regex): ?string
    {
        if (preg_match($regex, $titulo, $matches) !== 1) {
            return null;
        }

        return self::normalizarValor($matches[0]);
    }

    private static function extrairMedida(string $titulo): ?string
    {
        $regexes = [
            '/\b\d+(?:[,.]\d+)?\s*(?:mm|cm|m|kg|g)\s*(?:a|-)\s*\d+(?:[,.]\d+)?\s*(?:mm|cm|m|kg|g)\b/i',
            '/\b\d+\/\d+\s*-\s*\d+(?:[,.]\d+)?\s*(?:(?:pol|polegadas?)\b|["”])?/i',
            '/\b\d+\s+\d+\/\d+\s*(?:(?:pol|polegadas?)\b|["”])?/i',
            '/\b\d+[,.]\d+\/\d+\s*(?:(?:pol|polegadas?)\b|["”])?/i',
            '/\b\d+\/\d+\s*(?:(?:pol|polegadas?)\b|["”])?\s*x\s*\d+(?:[,.]\d+)?\s*(?:(?:pol|polegadas?)\b|["”])?/i',
            '/\b\d+(?:[,.]\d+)?\s*(?:(?:mm|cm|m|kg|g|pol|polegadas?)\b|["”])/i',
        ];

        foreach ($regexes as $regex) {
            if (preg_match($regex, $titulo, $matches) === 1) {
                return self::normalizarValor($matches[0]);
            }
        }

        return null;
    }

    private static function extrairMaterial(string $normalizado): ?string
    {
        $materiais = [
            'cromo vanadio',
            'aco inox',
            'aco carbono',
            'aco',
            'inox',
            'madeira',
            'borracha',
            'aluminio',
            'plastico',
            'fibra',
            'metal',
        ];

        foreach ($materiais as $material) {
            if (self::contemTermo($normalizado, $material)) {
                return $material;
            }
        }

        return null;
    }

    private static function extrairModelo(string $titulo): ?string
    {
        $semMarca = self::removerMarcaDoTitulo($titulo);

        if (preg_match('/\b[A-Z]{2,}[A-Z0-9-]*(?:\s+\d+[A-Z]?)?(?:\s+[A-Z]{2,})?\b/', $semMarca, $matches) !== 1) {
            return null;
        }

        $modelo = trim($matches[0]);

        if (in_array(self::normalizarParaMatch($modelo), ['v', 'w', 'mm', 'cm', 'kg'], true)) {
            return null;
        }

        return $modelo;
    }

    private static function removerMarcaDoTitulo(string $titulo): string
    {
        foreach (self::MARCAS as $marca) {
            $titulo = preg_replace('/\b'.preg_quote($marca, '/').'\b/i', ' ', $titulo) ?? $titulo;
        }

        return $titulo;
    }

    /**
     * @return string[]
     */
    private static function extrairCodigos(string $termo): array
    {
        $semMarcas = self::removerMarcaDoTitulo($termo);

        preg_match_all('/\b(?=[A-Z0-9.-]*\d)(?:[A-Z]+\d+[A-Z0-9.-]*|\d{5,}|\d{2,}[.-]\d{2,}|[A-Z0-9]+-[A-Z0-9-]+)\b/i', $semMarcas, $matches);

        $codigos = array_filter($matches[0] ?? [], function (string $codigo): bool {
            $normalizado = self::normalizarParaMatch($codigo);
            $codigoNormalizado = self::normalizarCodigo($codigo);

            if (preg_match('/^\d+(?:mm|cm|m|kg|g|v|w|hp|cv)$/i', $normalizado) === 1) {
                return false;
            }

            if (ctype_digit($codigoNormalizado) && strlen($codigoNormalizado) < 6) {
                return false;
            }

            return strlen($codigoNormalizado) >= 4;
        });

        return self::valoresUnicosNaoVazios($codigos);
    }

    /**
     * @param  string[]  $codigos
     */
    private static function removerCodigosDoTermo(string $termo, array $codigos): string
    {
        foreach ($codigos as $codigo) {
            $termo = preg_replace('/\b'.preg_quote($codigo, '/').'\b/i', ' ', $termo) ?? $termo;
        }

        return $termo;
    }

    private static function normalizarTermoParaLoja(string $termo): string
    {
        $termo = str_replace(['(', ')', '[', ']', '{', '}'], ' ', $termo);
        $termo = preg_replace('/(?<!\d)[\/,;]+|[\/,;]+(?!\d)/', ' ', $termo) ?? $termo;
        $termo = preg_replace('/\s+-\s*/', ' ', $termo) ?? $termo;
        $termo = preg_replace('/\s+-([A-Z]{2,})\b/i', ' $1', $termo) ?? $termo;
        $termo = preg_replace('/\s+/', ' ', trim($termo)) ?? '';

        return trim($termo);
    }

    /**
     * @param  string[]  $valores
     * @return string[]
     */
    private static function valoresUnicosNaoVazios(array $valores): array
    {
        $vistos = [];
        $unicos = [];

        foreach ($valores as $valor) {
            $valor = trim($valor);

            if ($valor === '') {
                continue;
            }

            $chave = self::normalizarParaMatch($valor);

            if (isset($vistos[$chave])) {
                continue;
            }

            $vistos[$chave] = true;
            $unicos[] = $valor;
        }

        return $unicos;
    }

    private static function normalizarValor(string $valor): string
    {
        $valor = str_replace(['”', '″'], '"', $valor);
        $valor = preg_replace('/\s+/', ' ', trim($valor)) ?? '';

        return $valor;
    }

    private static function removerAcentos(string $texto): string
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

        return strtr($texto, $map);
    }
}
