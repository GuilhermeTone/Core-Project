<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EcommerceAiNormalizerService
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'base_uri' => 'https://api.openai.com',
            'timeout' => max(1, (int) config('services.openai.timeout', 12)),
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }

    /**
     * @return array{original: string, normalizado: string, termos_busca: string[], confianca: float, origem: string}
     */
    public function normalizarBusca(string $termo): array
    {
        $termoLimpo = $this->limpar($termo);
        $fallback = $this->fallbackNormalizacao($termoLimpo);

        if (! $this->normalizacaoHabilitada() || $termoLimpo === '') {
            return $fallback;
        }

        $cacheKey = $this->cacheKey('normalizar', $termoLimpo);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $json = $this->criarRespostaJson(
                $this->instrucoesNormalizacao(),
                'q='.$termoLimpo,
                $this->schemaNormalizacao(),
            );

            $normalizado = $this->limpar((string) ($json['q'] ?? ''));
            $termos = $this->limparLista($json['qs'] ?? []);
            $confianca = $this->normalizarScore($json['c'] ?? 0.0);

            if ($normalizado === '' || $confianca < 0.45) {
                return $fallback;
            }

            $resultado = [
                'original' => $termoLimpo,
                'normalizado' => $normalizado,
                'termos_busca' => ! empty($termos) ? $termos : [$normalizado],
                'confianca' => $confianca,
                'origem' => 'openai',
            ];

            Cache::put($cacheKey, $resultado, $this->cacheTtl());

            return $resultado;
        } catch (\Throwable $e) {
            Log::warning('OpenAI normalizador de busca falhou.', [
                'erro' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    private function habilitado(): bool
    {
        return (bool) config('services.openai.enabled', false)
            && trim((string) config('services.openai.api_key', '')) !== '';
    }

    private function normalizacaoHabilitada(): bool
    {
        return $this->habilitado()
            && (bool) config('services.openai.normalize_search_enabled', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function criarRespostaJson(string $instructions, string $input, array $schema): array
    {
        $models = array_values(array_unique(array_filter([
            (string) config('services.openai.model', 'gpt-5.4-nano'),
            (string) config('services.openai.fallback_model', 'gpt-4.1-nano'),
        ])));

        foreach ($models as $index => $model) {
            $response = $this->client->post('/v1/responses', [
                'headers' => [
                    'Authorization' => 'Bearer '.config('services.openai.api_key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'instructions' => $instructions,
                    'input' => $input,
                    'max_output_tokens' => max(64, (int) config('services.openai.max_output_tokens', 220)),
                    'store' => false,
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => $schema['name'],
                            'strict' => true,
                            'schema' => $schema['schema'],
                        ],
                    ],
                ],
            ]);

            $body = (string) $response->getBody();

            if ($response->getStatusCode() < 400) {
                break;
            }

            $error = json_decode($body, true);
            $message = $error['error']['message'] ?? trim($body);
            $code = $error['error']['code'] ?? null;

            if ($code === 'model_not_found' && isset($models[$index + 1])) {
                Log::warning('OpenAI modelo principal indisponível; usando fallback.', [
                    'model' => $model,
                    'fallback_model' => $models[$index + 1],
                    'erro' => $message,
                ]);

                continue;
            }

            throw new \RuntimeException(trim('OpenAI retornou HTTP '.$response->getStatusCode().': '.$message.' '.($code ? "({$code})" : '')));
        }

        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw new \RuntimeException('Resposta inválida da OpenAI.');
        }

        $texto = $data['output_text'] ?? $this->textoDaResposta($data);
        $json = json_decode((string) $texto, true);

        if (! is_array($json)) {
            throw new \RuntimeException('JSON estruturado inválido da OpenAI.');
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function textoDaResposta(array $data): string
    {
        foreach ($data['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return (string) $content['text'];
                }
            }
        }

        return '';
    }

    private function instrucoesNormalizacao(): string
    {
        return implode(' ', [
            'Normalize buscas brasileiras de ferramentas para ecommerce.',
            'Corrija erros e nomes populares sem inventar atributos.',
            'Use termos curtos.',
            'Exemplos: griffo=>grifo; chave cruz=>chave phillips; chave estrela sem medida=>chave phillips;',
            'chave estrela com mm=>chave estrela; chave combinada/fixa/biela preserva medida.',
            'Preserve marca, modelo, codigo, voltagem e medidas.',
            'Responda apenas JSON.',
        ]);
    }

    /**
     * @return array{name: string, schema: array<string, mixed>}
     */
    private function schemaNormalizacao(): array
    {
        return [
            'name' => 'busca_normalizada',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'q' => ['type' => 'string'],
                    'qs' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 3,
                    ],
                    'c' => ['type' => 'number'],
                ],
                'required' => ['q', 'qs', 'c'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @return array{original: string, normalizado: string, termos_busca: string[], confianca: float, origem: string}
     */
    private function fallbackNormalizacao(string $termo): array
    {
        return [
            'original' => $termo,
            'normalizado' => $termo,
            'termos_busca' => $termo === '' ? [] : [$termo],
            'confianca' => 0.0,
            'origem' => 'local',
        ];
    }

    private function limpar(string $texto): string
    {
        $texto = preg_replace('/\s+/', ' ', trim($texto)) ?? '';

        return mb_substr($texto, 0, 180);
    }

    /**
     * @return string[]
     */
    private function limparLista(mixed $lista): array
    {
        if (! is_array($lista)) {
            return [];
        }

        $limpos = [];

        foreach ($lista as $item) {
            $limpo = $this->limpar((string) $item);

            if ($limpo !== '') {
                $limpos[] = $limpo;
            }
        }

        return array_values(array_unique(array_slice($limpos, 0, 3)));
    }

    private function normalizarScore(mixed $score): float
    {
        if (! is_numeric($score)) {
            return 0.0;
        }

        return max(0.0, min(1.0, (float) $score));
    }

    private function cacheKey(string $tipo, string $payload): string
    {
        return 'openai:ecommerce:v2:'.$tipo.':'.md5(mb_strtolower($payload, 'UTF-8'));
    }

    private function cacheTtl(): int
    {
        return max(60, (int) config('services.openai.cache_ttl', 86400));
    }
}
