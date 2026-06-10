<?php

namespace App\Services;

use GuzzleHttp\Client;
use RuntimeException;

class SerperShoppingService
{
    private const SCORE_MINIMO = 0.65;

    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://google.serper.dev',
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buscar(string $termo): array
    {
        $apiKey = (string) config('services.serper.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('SERPER_API_KEY não configurada.');
        }

        $response = $this->client->post('/shopping', [
            'headers' => [
                'X-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'q' => $termo,
                'gl' => 'br',
                'hl' => 'pt',
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('Serper retornou HTTP '.$response->getStatusCode().'.');
        }

        $data = json_decode((string) $response->getBody(), true);

        if (! is_array($data)) {
            throw new RuntimeException('Resposta inválida do Serper.');
        }

        $resultados = array_map(
            fn (array $item): array => $this->mapearItem($item),
            array_filter($data['shopping'] ?? [], 'is_array'),
        );

        $resultados = array_values(array_filter(
            $resultados,
            fn (array $item): bool => ! empty($item['nome'])
                && ! empty($item['url'])
                && (float) ($item['preco'] ?? 0) > 0
                && ($item['disponivel'] ?? true) !== false,
        ));

        $relevance = new SerperRelevanceService;
        $resultados = ProductEnrichmentService::enriquecerProdutos($resultados, $termo);
        $resultados = array_map(
            fn (array $item): array => $relevance->aplicar($termo, $item),
            $resultados,
        );
        $resultados = array_values(array_filter(
            $resultados,
            fn (array $item): bool => (float) ($item['score_produto'] ?? 0) >= self::SCORE_MINIMO,
        ));

        usort($resultados, fn (array $a, array $b): int => ((float) ($a['preco'] ?? PHP_FLOAT_MAX)) <=> ((float) ($b['preco'] ?? PHP_FLOAT_MAX)));

        return $resultados;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapearItem(array $item): array
    {
        $source = (string) ($item['source'] ?? 'Google Shopping');

        return [
            'site' => 'serper',
            'nome_site' => $source,
            'loja_origem' => $source,
            'nome' => (string) ($item['title'] ?? ''),
            'descricao' => (string) ($item['snippet'] ?? ''),
            'preco' => $this->parsePreco($item['price'] ?? null),
            'url' => (string) ($item['link'] ?? $item['productLink'] ?? ''),
            'imagem' => $item['imageUrl'] ?? $item['thumbnail'] ?? null,
            'codigo' => null,
            'disponivel' => $this->disponivel($item),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function disponivel(array $item): bool
    {
        foreach (['availability', 'available', 'inStock', 'stock'] as $campo) {
            if (! array_key_exists($campo, $item)) {
                continue;
            }

            $valor = mb_strtolower((string) $item[$campo], 'UTF-8');

            if (str_contains($valor, 'out of stock')
                || str_contains($valor, 'fora de estoque')
                || str_contains($valor, 'sem estoque')
                || str_contains($valor, 'indispon')) {
                return false;
            }
        }

        return true;
    }

    private function parsePreco(mixed $preco): ?float
    {
        if (is_numeric($preco)) {
            return (float) $preco;
        }

        $texto = trim((string) $preco);

        if ($texto === '') {
            return null;
        }

        if (! preg_match('/(\d{1,3}(?:\.\d{3})*|\d+)(?:,\d{2})?/', $texto, $match)) {
            return null;
        }

        $valor = str_replace('.', '', $match[0]);
        $valor = str_replace(',', '.', $valor);

        return is_numeric($valor) && (float) $valor > 0 ? (float) $valor : null;
    }
}
