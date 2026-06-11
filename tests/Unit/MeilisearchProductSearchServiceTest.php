<?php

namespace Tests\Unit;

use App\Services\MeilisearchProductSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeilisearchProductSearchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.meilisearch.enabled', false);
        config()->set('services.meilisearch.host', 'http://meili.test');
        config()->set('services.meilisearch.key', null);
        config()->set('services.meilisearch.task_wait_usleep', 1000);
        config()->set('services.meilisearch.min_ranking_score', 0.0);
    }

    public function test_retorna_produtos_originais_quando_meilisearch_desabilitado(): void
    {
        Http::fake();

        $produtos = [
            ['nome' => 'Chave Grifo 10"', 'preco' => 50.0],
        ];

        $resultado = (new MeilisearchProductSearchService)->filtrarERanquear(
            'chave griffo',
            'chave grifo',
            $produtos,
        );

        $this->assertSame('Chave Grifo 10"', $resultado[0]['nome']);
        $this->assertGreaterThan(0.0, $resultado[0]['score_produto']);
        Http::assertNothingSent();
    }

    public function test_filtra_e_ordena_produtos_pelos_hits_do_meilisearch(): void
    {
        config()->set('services.meilisearch.enabled', true);

        Http::fake([
            'http://meili.test/indexes' => Http::response(['taskUid' => 1]),
            'http://meili.test/indexes/*/settings' => Http::response(['taskUid' => 2]),
            'http://meili.test/indexes/*/documents' => Http::response(['taskUid' => 3]),
            'http://meili.test/indexes/*/search' => Http::response([
                'hits' => [
                    ['objectID' => '1', 'ordem_original' => 1, '_rankingScore' => 0.94],
                    ['objectID' => '0', 'ordem_original' => 0, '_rankingScore' => 0.81],
                ],
            ]),
            'http://meili.test/tasks/*' => Http::response(['status' => 'succeeded']),
            'http://meili.test/indexes/*' => Http::response(['taskUid' => 4]),
        ]);

        $produtos = [
            ['nome' => 'Alicate Universal', 'descricao' => null, 'preco' => 30.0],
            ['nome' => 'Chave Grifo 10"', 'descricao' => null, 'preco' => 50.0],
        ];

        $resultado = (new MeilisearchProductSearchService)->filtrarERanquear(
            'chave griffo',
            'chave grifo',
            $produtos,
        );

        $this->assertCount(2, $resultado);
        $this->assertSame('Chave Grifo 10"', $resultado[0]['nome']);
        $this->assertTrue($resultado[0]['match_meilisearch']);
        $this->assertSame(0.94, $resultado[0]['score_meilisearch']);
        $this->assertTrue($resultado[1]['match_meilisearch']);

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/search')
            && $request['matchingStrategy'] === 'last');
    }

    public function test_indexa_no_meilisearch_mesmo_com_um_unico_candidato(): void
    {
        config()->set('services.meilisearch.enabled', true);

        Http::fake([
            'http://meili.test/indexes' => Http::response(['taskUid' => 1]),
            'http://meili.test/indexes/*/settings' => Http::response(['taskUid' => 2]),
            'http://meili.test/indexes/*/documents' => Http::response(['taskUid' => 3]),
            'http://meili.test/indexes/*/search' => Http::response([
                'hits' => [
                    ['objectID' => '0', 'ordem_original' => 0, '_rankingScore' => 0.93],
                ],
            ]),
            'http://meili.test/tasks/*' => Http::response(['status' => 'succeeded']),
            'http://meili.test/indexes/*' => Http::response(['taskUid' => 4]),
        ]);

        $resultado = (new MeilisearchProductSearchService)->filtrarERanquear(
            'CHAVE PHILIPS 1/4" X 4" GEDORE',
            'chave philips 1/4 x 4 gedore',
            [
                ['nome' => 'Chave Philips 1/4" X 4" Gedore 036316', 'preco' => 10.66],
            ],
        );

        $this->assertCount(1, $resultado);
        $this->assertTrue($resultado[0]['match_meilisearch']);
        $this->assertSame(0.93, $resultado[0]['score_meilisearch']);

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/documents'));
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/search'));
    }

    public function test_mantem_candidatos_validos_mesmo_quando_meilisearch_retorna_poucos_hits(): void
    {
        config()->set('services.meilisearch.enabled', true);

        Http::fake([
            'http://meili.test/indexes' => Http::response(['taskUid' => 1]),
            'http://meili.test/indexes/*/settings' => Http::response(['taskUid' => 2]),
            'http://meili.test/indexes/*/documents' => Http::response(['taskUid' => 3]),
            'http://meili.test/indexes/*/search' => Http::response([
                'hits' => [
                    ['objectID' => '0', 'ordem_original' => 0, '_rankingScore' => 0.91],
                ],
            ]),
            'http://meili.test/tasks/*' => Http::response(['status' => 'succeeded']),
            'http://meili.test/indexes/*' => Http::response(['taskUid' => 4]),
        ]);

        $produtos = [
            ['nome' => 'Chave Philips Robust 1/4 x 4"', 'descricao' => null, 'preco' => 20.0],
            ['nome' => 'Chave Philips Gedore 1/4 x 4"', 'descricao' => null, 'preco' => 35.0],
        ];

        $resultado = (new MeilisearchProductSearchService)->filtrarERanquear(
            'CHAVE PHILIPS 1/4 X 4 GEDORE/BELZER/ROBUST',
            'chave philips 1/4 x 4 gedore belzer robust',
            $produtos,
        );

        $this->assertCount(2, $resultado);
        $this->assertTrue($resultado[0]['match_meilisearch']);
        $this->assertFalse($resultado[1]['match_meilisearch']);
        $this->assertGreaterThan(0.0, $resultado[1]['score_produto']);
    }

    public function test_remove_produto_com_medida_explicita_incompativel(): void
    {
        Http::fake();

        $produtos = [
            ['nome' => 'Chave Philips 1/8" X 4" Ph0 Simples 160', 'preco' => 7.88],
            ['nome' => 'Chave Philips 1/4" X 4" Robust', 'preco' => 18.50],
            ['nome' => 'Chave Philips Cabo Verde', 'preco' => 10.00],
        ];

        $resultado = (new MeilisearchProductSearchService)->filtrarERanquear(
            'CHAVE PHILIPS 1/4" X 4" GEDORE/BELZER/ROBUST',
            'chave philips 1/4 x 4 gedore belzer robust',
            $produtos,
        );

        $this->assertCount(1, $resultado);
        $this->assertSame('Chave Philips 1/4" X 4" Robust', $resultado[0]['nome']);
        $this->assertGreaterThan(0.0, $resultado[0]['score_produto']);
        Http::assertNothingSent();
    }

    public function test_respeita_marcas_solicitadas_e_remove_marcas_diferentes(): void
    {
        Http::fake();

        $produtos = [
            ['nome' => 'Chave philips 1/4" x 4" aço carbono NOVE54', 'preco' => 5.44],
            ['nome' => 'Chave Philips 1/4" X 4" sem marca informada', 'preco' => 8.90],
            ['nome' => 'Chave Philips 1/4" X 4" Robust', 'preco' => 18.50],
            ['nome' => 'Chave Philips 1/4" X 4" GEDORE RED', 'preco' => 22.00],
        ];

        $resultado = (new MeilisearchProductSearchService)->filtrarERanquear(
            'CHAVE PHILIPS 1/4" X 4" GEDORE/BELZER/ROBUST',
            'chave philips 1/4 x 4 gedore belzer robust',
            $produtos,
        );

        $this->assertSame([
            'Chave Philips 1/4" X 4" Robust',
            'Chave Philips 1/4" X 4" GEDORE RED',
        ], array_column($resultado, 'nome'));
        $this->assertSame(['Robust', 'Gedore RED'], array_column($resultado, 'marca_detectada'));
        Http::assertNothingSent();
    }
}
