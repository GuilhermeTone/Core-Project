<?php

namespace Tests\Unit;

use App\Services\CrawlerService;
use App\Services\EcommerceAiNormalizerService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EcommerceAiNormalizerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.openai.enabled', false);
        config()->set('services.openai.normalize_search_enabled', true);
        config()->set('services.openai.api_key', null);
        config()->set('services.openai.cache_ttl', 60);
    }

    public function test_normalizar_busca_usa_fallback_quando_openai_desabilitada(): void
    {
        $service = new EcommerceAiNormalizerService;

        $resultado = $service->normalizarBusca(' chave   griffo  ');

        $this->assertSame('chave griffo', $resultado['normalizado']);
        $this->assertSame(['chave griffo'], $resultado['termos_busca']);
        $this->assertSame('local', $resultado['origem']);
    }

    public function test_container_resolve_servicos_do_crawler_com_normalizador(): void
    {
        $this->assertInstanceOf(EcommerceAiNormalizerService::class, $this->app->make(EcommerceAiNormalizerService::class));
        $this->assertInstanceOf(CrawlerService::class, $this->app->make(CrawlerService::class));
    }

    public function test_normalizar_busca_usa_json_estruturado_da_openai(): void
    {
        config()->set('services.openai.enabled', true);
        config()->set('services.openai.api_key', 'test-key');

        $service = new EcommerceAiNormalizerService($this->clientComRespostas([
            ['output_text' => json_encode([
                'q' => 'chave grifo',
                'qs' => ['chave grifo'],
                'c' => 0.95,
            ])],
        ]));

        $resultado = $service->normalizarBusca('chave griffo');

        $this->assertSame('chave grifo', $resultado['normalizado']);
        $this->assertSame(['chave grifo'], $resultado['termos_busca']);
        $this->assertSame('openai', $resultado['origem']);
    }

    public function test_normalizar_busca_usa_fallback_quando_normalizacao_desabilitada(): void
    {
        config()->set('services.openai.enabled', true);
        config()->set('services.openai.normalize_search_enabled', false);
        config()->set('services.openai.api_key', 'test-key');

        $service = new EcommerceAiNormalizerService($this->clientComRespostas([]));

        $resultado = $service->normalizarBusca('chave griffo');

        $this->assertSame('chave griffo', $resultado['normalizado']);
        $this->assertSame(['chave griffo'], $resultado['termos_busca']);
        $this->assertSame('local', $resultado['origem']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $respostas
     */
    private function clientComRespostas(array $respostas): Client
    {
        $mock = new MockHandler(array_map(
            fn (array $body): Response => new Response(200, [], json_encode($body)),
            $respostas,
        ));

        return new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://api.openai.com',
            'http_errors' => false,
        ]);
    }
}
