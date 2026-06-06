<?php

namespace Tests\Unit\Planilhas;

use App\Models\PlanilhaCotacaoItem;
use App\Services\CrawlerService;
use App\Services\Planilhas\PlanilhaRevalidacaoService;
use Mockery;
use Tests\TestCase;

class PlanilhaRevalidacaoServiceTest extends TestCase
{
    public function test_revalidacao_atualiza_preco_da_loja_e_valor_final_com_margem(): void
    {
        /** @var PlanilhaCotacaoItem&\Mockery\MockInterface $item */
        $item = Mockery::mock(PlanilhaCotacaoItem::class)->makePartial();
        $item->setRawAttributes([
            'linha' => 4,
            'descricao' => 'CHAVE COMBINADA 1/4 GEDORE',
            'status' => 'concluido',
            'marca_cotada' => 'Gedore',
            'preco_loja' => 10.00,
            'valor_unitario' => 12.00,
            'margem_percentual' => 20,
        ]);
        $item->resultado_escolhido = [
            'site' => 'loja_teste',
            'nome' => 'Chave Combinada 1/4 Gedore',
            'url' => 'https://loja.test/produto/chave-combinada-gedore',
            'preco' => 10.00,
            'marca_detectada' => 'Gedore',
        ];

        $item->shouldReceive('update')
            ->once()
            ->andReturnUsing(function (array $atributos) use ($item): bool {
                foreach ($atributos as $chave => $valor) {
                    $item->{$chave} = $valor;
                }

                return true;
            });

        $crawler = new class extends CrawlerService
        {
            public function __construct() {}

            public function buscarEmLoja(string $termo, string $identificador): array
            {
                return [[
                    'site' => $identificador,
                    'nome' => 'Chave Combinada 1/4 Gedore',
                    'url' => 'https://loja.test/produto/chave-combinada-gedore?tracking=abc',
                    'preco' => 15.00,
                    'marca_detectada' => 'Gedore',
                    'score_produto' => 0.95,
                ]];
            }
        };

        $resultado = (new PlanilhaRevalidacaoService($crawler))->revalidarItem($item);

        $this->assertSame('preco_alterado', $resultado['status']);
        $this->assertSame('preco_alterado', $item->revalidacao_status);
        $this->assertSame(15.00, (float) $item->preco_loja);
        $this->assertSame(15.00, (float) $item->preco_revalidado);
        $this->assertSame(18.00, (float) $item->valor_unitario);
        $this->assertSame(15.00, (float) $item->resultado_escolhido['preco']);
    }
}
