<?php

namespace Tests\Unit;

use App\Services\SerperRelevanceService;
use Tests\TestCase;

class SerperRelevanceServiceTest extends TestCase
{
    public function test_aceita_produto_especifico_com_marca_cor_e_volume(): void
    {
        $service = new SerperRelevanceService;

        $produto = $service->aplicar('Tinta amarelo Maza 3,6L', [
            'nome' => 'Tinta Acrilica Maza Amarelo 3,6L',
            'descricao' => 'Galão de tinta amarelo para parede',
        ]);

        $this->assertGreaterThanOrEqual(0.70, $produto['score_produto']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_rejeita_produto_com_marca_especifica_diferente(): void
    {
        $service = new SerperRelevanceService;

        $produto = $service->aplicar('Tinta amarelo Maza 3,6L', [
            'nome' => 'Tinta Acrilica Coral Amarelo 3,6L',
            'descricao' => 'Galão de tinta amarelo para parede',
        ]);

        $this->assertLessThan(0.70, $produto['score_produto']);
        $this->assertTrue($produto['correspondencia_fraca']);
    }

    public function test_rejeita_produto_com_volume_diferente(): void
    {
        $service = new SerperRelevanceService;

        $produto = $service->aplicar('Tinta amarelo Maza 3,6L', [
            'nome' => 'Tinta Acrilica Maza Amarelo 18L',
            'descricao' => 'Lata grande de tinta amarelo',
        ]);

        $this->assertLessThan(0.70, $produto['score_produto']);
        $this->assertTrue($produto['correspondencia_fraca']);
    }

    public function test_aceita_busca_sem_marca_com_atributos_compativeis(): void
    {
        $service = new SerperRelevanceService;

        $produto = $service->aplicar('torneira cozinha parede cromada', [
            'nome' => 'Torneira de Parede para Cozinha Cromada',
            'descricao' => 'Bica movel metal cromado',
        ]);

        $this->assertGreaterThanOrEqual(0.70, $produto['score_produto']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }
}
