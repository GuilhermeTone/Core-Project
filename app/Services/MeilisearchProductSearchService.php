<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MeilisearchProductSearchService
{
    /**
     * @param  array<int, array<string, mixed>>  $produtos
     * @return array<int, array<string, mixed>>
     */
    public function filtrarERanquear(string $buscaOriginal, string $buscaNormalizada, array $produtos): array
    {
        $produtos = array_values($produtos);
        $produtos = $this->removerMedidasIncompativeis($buscaOriginal, $buscaNormalizada, $produtos);
        $produtos = $this->filtrarPorMarcasSolicitadas($buscaOriginal, $buscaNormalizada, $produtos);
        $produtos = $this->aplicarScoreProduto($buscaOriginal, $buscaNormalizada, $produtos);

        if (! $this->habilitado() || empty($produtos)) {
            return $produtos;
        }

        $indice = $this->indiceTemporario();

        try {
            $this->criarIndice($indice);
            $this->configurarIndice($indice);
            $this->indexarProdutos($indice, $produtos);

            return $this->buscarProdutos($indice, $this->termosBusca($buscaOriginal, $buscaNormalizada), $produtos);
        } catch (\Throwable $e) {
            Log::warning('Meilisearch avaliador de produtos falhou; mantendo resultados do crawler.', [
                'erro' => $e->getMessage(),
            ]);

            return $produtos;
        } finally {
            $this->removerIndice($indice);
        }
    }

    private function habilitado(): bool
    {
        return (bool) config('services.meilisearch.enabled', false)
            && trim((string) config('services.meilisearch.host', '')) !== '';
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.meilisearch.host'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(max(1, (int) config('services.meilisearch.timeout', 5)));

        $key = trim((string) config('services.meilisearch.key', ''));

        return $key !== '' ? $request->withToken($key) : $request;
    }

    private function indiceTemporario(): string
    {
        $prefixo = preg_replace(
            '/[^a-zA-Z0-9_-]/',
            '_',
            (string) config('services.meilisearch.temp_index_prefix', 'crawler_candidates'),
        ) ?: 'crawler_candidates';

        return $prefixo.'_'.str_replace('-', '_', (string) Str::uuid());
    }

    private function criarIndice(string $indice): void
    {
        $response = $this->request()->post('/indexes', [
            'uid' => $indice,
            'primaryKey' => 'objectID',
        ]);

        $this->waitTask($response->throw()->json('taskUid'));
    }

    private function configurarIndice(string $indice): void
    {
        $response = $this->request()->patch("/indexes/{$indice}/settings", [
            'searchableAttributes' => [
                'nome',
                'descricao',
                'codigo',
                'marca',
                'categoria',
                'tipo',
                'medida',
                'texto',
            ],
            'displayedAttributes' => [
                'objectID',
                'ordem_original',
            ],
            'typoTolerance' => [
                'enabled' => true,
            ],
        ]);

        $this->waitTask($response->throw()->json('taskUid'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $produtos
     */
    private function indexarProdutos(string $indice, array $produtos): void
    {
        $response = $this->request()->post(
            "/indexes/{$indice}/documents",
            array_map(fn (array $produto, int $i): array => $this->documento($produto, $i), $produtos, array_keys($produtos)),
        );

        $this->waitTask($response->throw()->json('taskUid'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $produtos
     * @param  array<int, string>  $termos
     * @return array<int, array<string, mixed>>
     */
    private function buscarProdutos(string $indice, array $termos, array $produtos): array
    {
        $resultado = [];
        $incluidos = [];

        foreach ($termos as $termo) {
            $response = $this->request()->post("/indexes/{$indice}/search", [
                'q' => $termo,
                'limit' => $this->limite(),
                'matchingStrategy' => (string) config('services.meilisearch.matching_strategy', 'last'),
                'showRankingScore' => true,
            ]);

            $hits = $response->throw()->json('hits') ?? [];

            foreach ($hits as $hit) {
                if (! is_array($hit)) {
                    continue;
                }

                $indiceOriginal = (int) ($hit['ordem_original'] ?? -1);

                if (! array_key_exists($indiceOriginal, $produtos) || isset($incluidos[$indiceOriginal])) {
                    continue;
                }

                $score = $hit['_rankingScore'] ?? null;

                if (is_numeric($score) && (float) $score < $this->scoreMinimo()) {
                    continue;
                }

                $produto = $produtos[$indiceOriginal];
                $produto['match_meilisearch'] = true;
                $produto['score_meilisearch'] = is_numeric($score) ? round((float) $score, 4) : null;
                $resultado[] = $produto;
                $incluidos[$indiceOriginal] = true;
            }
        }

        foreach ($produtos as $indiceOriginal => $produto) {
            if (isset($incluidos[$indiceOriginal]) || count($resultado) >= $this->limite()) {
                continue;
            }

            $produto['match_meilisearch'] = false;
            $produto['score_meilisearch'] = $produto['score_meilisearch'] ?? null;
            $resultado[] = $produto;
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $produto
     */
    private function documento(array $produto, int $i): array
    {
        $atributos = is_array($produto['atributos_extraidos'] ?? null) ? $produto['atributos_extraidos'] : [];

        return [
            'objectID' => (string) $i,
            'ordem_original' => $i,
            'nome' => (string) ($produto['nome'] ?? ''),
            'descricao' => (string) ($produto['descricao'] ?? ''),
            'codigo' => (string) ($produto['codigo'] ?? ''),
            'marca' => (string) ($produto['marca_detectada'] ?? ''),
            'categoria' => (string) ($atributos['categoria'] ?? ''),
            'tipo' => (string) ($atributos['tipo'] ?? ''),
            'medida' => (string) ($atributos['medida'] ?? ''),
            'texto' => implode(' ', array_filter(array_map(
                fn (mixed $valor): string => is_scalar($valor) ? (string) $valor : '',
                $atributos,
            ))),
        ];
    }

    private function waitTask(mixed $taskUid): void
    {
        if (! is_numeric($taskUid)) {
            return;
        }

        $tentativas = max(1, (int) config('services.meilisearch.task_wait_attempts', 20));

        for ($i = 0; $i < $tentativas; $i++) {
            $task = $this->request()->get('/tasks/'.(int) $taskUid)->throw()->json();
            $status = is_array($task) ? ($task['status'] ?? null) : null;

            if ($status === 'succeeded') {
                return;
            }

            if ($status === 'failed') {
                throw new \RuntimeException((string) ($task['error']['message'] ?? 'Task do Meilisearch falhou.'));
            }

            usleep(max(1000, (int) config('services.meilisearch.task_wait_usleep', 100000)));
        }

        throw new \RuntimeException('Timeout aguardando indexacao do Meilisearch.');
    }

    private function removerIndice(string $indice): void
    {
        try {
            $this->request()->delete("/indexes/{$indice}");
        } catch (\Throwable) {
            // Indice temporario: falha de limpeza nao deve derrubar a busca.
        }
    }

    /**
     * @return array<int, string>
     */
    private function termosBusca(string $buscaOriginal, string $buscaNormalizada): array
    {
        $termo = trim($buscaNormalizada) !== '' ? $buscaNormalizada : $buscaOriginal;
        $termos = [mb_substr(trim($termo), 0, 180)];

        $semMarcas = $this->removerMarcas($termo);

        if ($semMarcas !== $termo) {
            $termos[] = mb_substr($semMarcas, 0, 180);
        }

        return array_values(array_unique(array_filter(array_map('trim', $termos))));
    }

    private function removerMarcas(string $termo): string
    {
        if (count(ProductEnrichmentService::detectarMarcas($termo)) <= 1) {
            return $termo;
        }

        foreach (ProductEnrichmentService::marcasConhecidas() as $marca) {
            $termo = preg_replace('/\b'.preg_quote($marca, '/').'\b/i', ' ', $termo) ?? $termo;
        }

        $termo = preg_replace('/[\/|]+/', ' ', $termo) ?? $termo;
        $termo = preg_replace('/\s+/', ' ', $termo) ?? $termo;

        return trim($termo);
    }

    /**
     * @param  array<int, array<string, mixed>>  $produtos
     * @return array<int, array<string, mixed>>
     */
    private function filtrarPorMarcasSolicitadas(string $buscaOriginal, string $buscaNormalizada, array $produtos): array
    {
        $marcasBusca = ProductEnrichmentService::detectarMarcas($buscaOriginal.' '.$buscaNormalizada);

        if (empty($marcasBusca)) {
            return $produtos;
        }

        $compativeis = [];
        $semMarca = [];

        foreach ($produtos as $produto) {
            $marca = $this->marcaDoProduto($produto);

            if ($marca === null) {
                $semMarca[] = $produto;
                continue;
            }

            $produto['marca_detectada'] = $marca;

            if ($this->marcaCompativel($marca, $marcasBusca)) {
                $compativeis[] = $produto;
            }
        }

        if (! empty($compativeis)) {
            return array_values($compativeis);
        }

        return array_values($semMarca);
    }

    /**
     * @param  array<string, mixed>  $produto
     */
    private function marcaDoProduto(array $produto): ?string
    {
        $marca = trim((string) ($produto['marca_detectada'] ?? ''));

        if ($marca !== '') {
            return $marca;
        }

        $detectada = ProductEnrichmentService::detectarMarca(implode(' ', array_filter([
            $produto['nome'] ?? null,
            $produto['descricao'] ?? null,
            $produto['codigo'] ?? null,
        ], fn (mixed $valor): bool => is_scalar($valor) && trim((string) $valor) !== '')));

        return $detectada['marca_detectada'] ?? null;
    }

    /**
     * @param  array<int, string>  $marcasBusca
     */
    private function marcaCompativel(string $marcaProduto, array $marcasBusca): bool
    {
        $produto = ProductEnrichmentService::normalizarTexto($marcaProduto);

        foreach ($marcasBusca as $marcaBusca) {
            $busca = ProductEnrichmentService::normalizarTexto($marcaBusca);

            if ($produto === $busca
                || str_starts_with($produto, $busca.' ')
                || str_starts_with($busca, $produto.' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $produtos
     * @return array<int, array<string, mixed>>
     */
    private function aplicarScoreProduto(string $buscaOriginal, string $buscaNormalizada, array $produtos): array
    {
        $busca = trim($buscaNormalizada) !== '' ? $buscaNormalizada : $buscaOriginal;

        if (trim($busca) === '') {
            return $produtos;
        }

        return array_map(function (array $produto) use ($busca): array {
            $score = ProductEnrichmentService::calcularScoreProduto($busca, $produto);

            $produto['score_produto'] = max((float) ($produto['score_produto'] ?? 0), $score);

            return $produto;
        }, $produtos);
    }

    /**
     * @param  array<int, array<string, mixed>>  $produtos
     * @return array<int, array<string, mixed>>
     */
    private function removerMedidasIncompativeis(string $buscaOriginal, string $buscaNormalizada, array $produtos): array
    {
        $medidasBusca = $this->medidasNoTexto($buscaOriginal.' '.$buscaNormalizada);

        if (empty($medidasBusca)) {
            return $produtos;
        }

        return array_values(array_filter($produtos, function (array $produto) use ($medidasBusca): bool {
            $medidasProduto = $this->medidasNoProduto($produto);

            if (empty($medidasProduto)) {
                return true;
            }

            $fracoesBusca = $this->fracoes($medidasBusca);
            $fracoesProduto = $this->fracoes($medidasProduto);

            if (! empty($fracoesBusca) && ! empty($fracoesProduto) && empty(array_intersect($fracoesBusca, $fracoesProduto))) {
                return false;
            }

            return ! empty(array_intersect($medidasBusca, $medidasProduto));
        }));
    }

    /**
     * @param  array<string, mixed>  $produto
     * @return array<int, string>
     */
    private function medidasNoProduto(array $produto): array
    {
        $atributos = is_array($produto['atributos_extraidos'] ?? null) ? $produto['atributos_extraidos'] : [];
        $texto = implode(' ', array_filter([
            $produto['nome'] ?? null,
            $produto['descricao'] ?? null,
            $atributos['medida'] ?? null,
            $atributos['peso'] ?? null,
            $atributos['modelo'] ?? null,
        ], fn (mixed $valor): bool => is_scalar($valor) && trim((string) $valor) !== ''));

        return $this->medidasNoTexto($texto);
    }

    /**
     * @return array<int, string>
     */
    private function medidasNoTexto(string $texto): array
    {
        $normalizado = mb_strtolower($texto, 'UTF-8');
        $normalizado = str_replace(['"', '”', '″'], ' in ', $normalizado);
        $normalizado = str_replace(['polegadas', 'polegada', 'pol'], 'in', $normalizado);
        $medidas = [];

        if (preg_match_all('/\b(?:\d+\s+)?\d+\/\d+\s*x\s*\d+(?:[,.]\d+)?\b/', $normalizado, $matches)) {
            foreach ($matches[0] as $medida) {
                $medidas[] = $this->normalizarMedida($medida);
            }
        }

        if (preg_match_all('/\b\d+(?:[,.]\d+)?(?:\/\d+)?\s*(?:mm|cm|m|in|kg|g|l|ml)\b/', $normalizado, $matches)) {
            foreach ($matches[0] as $medida) {
                $medidas[] = $this->normalizarMedida($medida);
            }
        }

        if (preg_match_all('/\b\d+\/\d+\b/', $normalizado, $matches)) {
            foreach ($matches[0] as $medida) {
                $medidas[] = $this->normalizarMedida($medida);
            }
        }

        return array_values(array_unique(array_filter($medidas)));
    }

    /**
     * @param  array<int, string>  $medidas
     * @return array<int, string>
     */
    private function fracoes(array $medidas): array
    {
        return array_values(array_unique(array_filter(array_map(function (string $medida): ?string {
            if (! preg_match('/\b\d+\/\d+\b/', $medida, $match)) {
                return null;
            }

            return $match[0];
        }, $medidas))));
    }

    private function normalizarMedida(string $medida): string
    {
        $medida = str_replace(',', '.', mb_strtolower($medida));
        $medida = preg_replace('/\s+/', '', $medida) ?? $medida;

        return trim($medida);
    }

    private function limite(): int
    {
        return max(1, min(100, (int) config('services.meilisearch.max_results', 30)));
    }

    private function scoreMinimo(): float
    {
        return max(0.0, min(1.0, (float) config('services.meilisearch.min_ranking_score', 0.0)));
    }
}
