<?php

namespace Tests\Unit;

use App\Models\Produto;
use App\Services\ProdutoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdutoServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_verifica_se_ao_mudar_tipo_produto_valor_muda_te(): void
    {
        $produtoService = new ProdutoService();
        
        $valorMudado = $produtoService->adicionaValorDeAcordoComTipo(100, "eletronico");
        $this->assertEquals(120,$valorMudado);
    }
    public function test_verificia_se_produto_foi_inserido()
    {
        $produtoService = new ProdutoService();

        $produto = $produtoService->adicionaProduto("Notebook", 1000, "eletronico");

        $this->assertEquals("Notebook", $produto->nome);
        $this->assertEquals(1000, $produto->valor);
        $this->assertEquals("eletronico", $produto->tipo);
    }

    public function test_verificia_se_produto_foi_removido()
    {
        $produtoService = new ProdutoService();

        $produto = $produtoService->adicionaProduto("Notebook", 1000, "eletronico");
        $produto->delete();

        $this->assertDatabaseMissing('produtos', [
            'id' => $produto->id,
            'nome' => 'Notebook',
            'valor' => 1000,
            'tipo' => 'eletronico'
        ]);
    }
}
