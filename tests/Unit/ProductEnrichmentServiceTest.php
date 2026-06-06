<?php

namespace Tests\Unit;

use App\Services\ProductEnrichmentService;
use PHPUnit\Framework\TestCase;

class ProductEnrichmentServiceTest extends TestCase
{
    public function test_normalizar_texto_remove_acentos_caracteres_especiais_e_espacos(): void
    {
        $normalizado = ProductEnrichmentService::normalizarTexto('  Chavé   Combináda!!!  Black+Decker  ');

        $this->assertSame('chave combinada black decker', $normalizado);
    }

    public function test_detectar_marca_suporta_nome_composto(): void
    {
        $marca = ProductEnrichmentService::detectarMarca('Furadeira Black+Decker 550W 220V');

        $this->assertSame('Black+Decker', $marca['marca_detectada']);
        $this->assertEqualsWithDelta(1.0, $marca['score_confianca'], 0.01);
    }

    public function test_detectar_novas_marcas_trabalhadas(): void
    {
        $casos = [
            'Serra Copo Gedore RED 32mm' => 'Gedore RED',
            'Alicate ROCAST 8 polegadas' => 'Rocast',
            'Chave Combinada BREMEN 10mm' => 'Bremen',
            'Compressor MOTOMIL 2hp' => 'Motomil',
            'Carrinho FERCAR 60 litros' => 'Fercar',
            'Jogo de Chaves SPARTA 12 Peças' => 'Sparta',
            'Chave Philips EDA 1/4' => 'EDA',
            'Martelo BRASFORT Cabo Madeira' => 'Brasfort',
            'Alicate Universal ROBUST 8 Pol.' => 'Robust',
            'Extrator RAVEN 2 Garras' => 'Raven',
            'Chave de Fenda TENACE 1/4' => 'Tenace',
            'Chave Combinada SATA 13mm' => 'SATA',
            'Alicate Universal MTX 8 Pol.' => 'MTX',
            'Macaco Hidraulico FERRAR 2T' => 'Ferrar',
            'Prensa Hidraulica MARCON 15T' => 'Marcon',
        ];

        foreach ($casos as $titulo => $marcaEsperada) {
            $marca = ProductEnrichmentService::detectarMarca($titulo);

            $this->assertSame($marcaEsperada, $marca['marca_detectada'], $titulo);
            $this->assertEqualsWithDelta(1.0, $marca['score_confianca'], 0.01, $titulo);
        }
    }

    public function test_detectar_multiplas_marcas_na_busca(): void
    {
        $marcas = ProductEnrichmentService::detectarMarcas('GEDORE/BELZER/ROBUST');

        $this->assertSame(['Gedore', 'Belzer', 'Robust'], $marcas);
    }

    public function test_termos_busca_por_marca_expande_busca_com_multiplas_marcas(): void
    {
        $termos = ProductEnrichmentService::termosBuscaPorMarca('Alicate Universal GEDORE/BELZER/ROBUST');

        $this->assertSame([
            'Alicate Universal Gedore',
            'Alicate Universal Belzer',
            'Alicate Universal Robust',
        ], $termos);
    }

    public function test_termos_busca_preserva_medida_ao_expandir_multiplas_marcas(): void
    {
        $termos = ProductEnrichmentService::termosBuscaPorMarca('CHAVE PHILIPS 1/4" X 4" GEDORE/BELZER/ROBUST');

        $this->assertSame([
            'CHAVE PHILIPS 1/4" X 4" Gedore',
            'CHAVE PHILIPS 1/4" X 4" Belzer',
            'CHAVE PHILIPS 1/4" X 4" Robust',
        ], $termos);
    }

    public function test_termos_busca_expande_codigo_com_e_sem_codigo(): void
    {
        $termos = ProductEnrichmentService::termosBuscaPorMarca('ALICATE CORTE 6" TRAMONTINA 41006106');

        $this->assertSame([
            'ALICATE CORTE 6" 41006106 Tramontina',
            'ALICATE CORTE 6" Tramontina',
            'Tramontina 41006106',
            '41006106',
        ], $termos);
    }

    public function test_extrair_codigos_da_busca_ignora_medidas_e_marcas(): void
    {
        $this->assertSame([], ProductEnrichmentService::extrairCodigosDaBusca('CHAVE GRIFO 18" (GEDORE/BELZER/ROBUST)'));
        $this->assertSame(['41006106'], ProductEnrichmentService::extrairCodigosDaBusca('ALICATE CORTE 6" TRAMONTINA 41006106'));
    }

    public function test_martelo_unha_tramontina_27mm_cabo_madeira(): void
    {
        $titulo = 'Martelo Unha Tramontina 27mm Cabo Madeira';
        $produto = ProductEnrichmentService::enriquecerProduto(['nome' => $titulo], 'martelo unha tramontina 27mm');

        $this->assertSame('Tramontina', $produto['marca_detectada']);
        $this->assertSame('ferramenta manual', $produto['atributos_extraidos']['categoria']);
        $this->assertSame('martelo unha', $produto['atributos_extraidos']['tipo']);
        $this->assertSame('27mm', $produto['atributos_extraidos']['medida']);
        $this->assertSame('madeira', $produto['atributos_extraidos']['material']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_furadeira_de_impacto_bosch_gsb_13_re_750w_220v(): void
    {
        $titulo = 'Furadeira de Impacto Bosch GSB 13 RE 750W 220V';
        $produto = ProductEnrichmentService::enriquecerProduto(['nome' => $titulo], 'furadeira impacto bosch gsb 13 re 220v');

        $this->assertSame('Bosch', $produto['marca_detectada']);
        $this->assertSame('ferramenta eletrica', $produto['atributos_extraidos']['categoria']);
        $this->assertSame('furadeira de impacto', $produto['atributos_extraidos']['tipo']);
        $this->assertSame('GSB 13 RE', $produto['atributos_extraidos']['modelo']);
        $this->assertSame('750W', $produto['atributos_extraidos']['potencia']);
        $this->assertSame('220V', $produto['atributos_extraidos']['voltagem']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_parafusadeira_makita_12v(): void
    {
        $titulo = 'Parafusadeira Makita 12V';
        $produto = ProductEnrichmentService::enriquecerProduto(['nome' => $titulo], 'parafusadeira makita 12v');

        $this->assertSame('Makita', $produto['marca_detectada']);
        $this->assertSame('ferramenta eletrica', $produto['atributos_extraidos']['categoria']);
        $this->assertSame('parafusadeira', $produto['atributos_extraidos']['tipo']);
        $this->assertSame('12V', $produto['atributos_extraidos']['voltagem']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_esmerilhadeira_angular_dewalt_4_1_2(): void
    {
        $titulo = 'Esmerilhadeira Angular Dewalt 4 1/2"';
        $produto = ProductEnrichmentService::enriquecerProduto(['nome' => $titulo], 'esmerilhadeira angular dewalt 4 1/2');

        $this->assertSame('Dewalt', $produto['marca_detectada']);
        $this->assertSame('ferramenta eletrica', $produto['atributos_extraidos']['categoria']);
        $this->assertSame('esmerilhadeira angular', $produto['atributos_extraidos']['tipo']);
        $this->assertSame('4 1/2"', $produto['atributos_extraidos']['medida']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_jogo_de_chaves_stanley_10_pecas(): void
    {
        $titulo = 'Jogo de Chaves Stanley 10 Peças';
        $produto = ProductEnrichmentService::enriquecerProduto(['nome' => $titulo], 'jogo chaves stanley 10 pecas');

        $this->assertSame('Stanley', $produto['marca_detectada']);
        $this->assertSame('ferramenta manual', $produto['atributos_extraidos']['categoria']);
        $this->assertSame('jogo de chaves', $produto['atributos_extraidos']['tipo']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_score_baixo_marca_correspondencia_fraca(): void
    {
        $produto = ProductEnrichmentService::enriquecerProduto(
            ['nome' => 'Disco de Corte Vonder 115mm'],
            'furadeira impacto bosch 220v',
        );

        $this->assertLessThan(0.70, $produto['score_produto']);
        $this->assertTrue($produto['correspondencia_fraca']);
    }

    public function test_marca_diferente_da_busca_vira_correspondencia_fraca(): void
    {
        $produto = ProductEnrichmentService::enriquecerProduto(
            ['nome' => 'Alicate Universal 8 Pol. TRAMONTINA-41001108'],
            'alicate universal bosch',
        );

        $this->assertSame('Tramontina', $produto['marca_detectada']);
        $this->assertSame(0.0, $produto['score_produto']);
        $this->assertTrue($produto['correspondencia_fraca']);
    }

    public function test_busca_com_multiplas_marcas_aceita_qualquer_marca_listada(): void
    {
        $produtos = ProductEnrichmentService::enriquecerProdutos([
            ['nome' => 'Alicate Universal GEDORE 8 Pol.'],
            ['nome' => 'Alicate Universal BELZER 8 Pol.'],
            ['nome' => 'Alicate Universal ROBUST 8 Pol.'],
            ['nome' => 'Alicate Universal TRAMONTINA 8 Pol.'],
        ], 'alicate universal GEDORE/BELZER/ROBUST');

        $filtrados = ProductEnrichmentService::filtrarProdutosConfiaveis($produtos);

        $this->assertCount(3, $filtrados);
        $this->assertSame(['Gedore', 'Belzer', 'Robust'], array_column($filtrados, 'marca_detectada'));
    }

    public function test_busca_de_planilha_com_medida_e_multiplas_marcas_passa(): void
    {
        $produto = ProductEnrichmentService::enriquecerProduto(
            ['nome' => 'Chave Philips 1/4 x 4 Belzer'],
            'CHAVE PHILIPS 1/4" X 4" GEDORE/BELZER/ROBUST',
        );

        $this->assertSame('Belzer', $produto['marca_detectada']);
        $this->assertSame('chave philips', $produto['atributos_extraidos']['tipo']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_busca_de_planilha_com_codigo_do_fabricante_passa_por_codigo(): void
    {
        $produto = ProductEnrichmentService::enriquecerProduto(
            ['nome' => 'Alicate Corte Diagonal 6 Pol. Tramontina', 'codigo' => '41006106'],
            'ALICATE CORTE 6" TRAMONTINA 41006106',
        );

        $this->assertSame('Tramontina', $produto['marca_detectada']);
        $this->assertSame(1.0, $produto['score_produto']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_jogo_chave_estrela_com_intervalo_hifen_passa_sem_marca_na_busca(): void
    {
        $produto = ProductEnrichmentService::enriquecerProduto(
            ['nome' => 'Jogo Chave Estrela 6-32mm 12 Pçs - 3365207 GEDORE RED', 'codigo' => '3365207'],
            'JOGO CHAVE ESTRELA 6MM A 32MM',
        );

        $this->assertSame('Gedore RED', $produto['marca_detectada']);
        $this->assertGreaterThanOrEqual(0.70, $produto['score_produto']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_jogo_chave_estrela_com_intervalo_diferente_nao_passa(): void
    {
        $produto = ProductEnrichmentService::enriquecerProduto(
            ['nome' => 'Jogo de Chave Estrela 6 - 22 mm (8 Pçs) - MAYLE'],
            'JOGO CHAVE ESTRELA 6MM A 32MM',
        );

        $this->assertSame(0.0, $produto['score_produto']);
        $this->assertTrue($produto['correspondencia_fraca']);
    }

    public function test_busca_com_marca_rejeita_produto_sem_marca_detectada(): void
    {
        $produto = ProductEnrichmentService::enriquecerProduto(
            ['nome' => 'Alicate Universal 08 Polegadas XPTO-169069'],
            'alicate universal bosch',
        );

        $this->assertNull($produto['marca_detectada']);
        $this->assertSame(0.0, $produto['score_produto']);
        $this->assertTrue($produto['correspondencia_fraca']);
    }

    public function test_chave_combinada_nao_casa_com_chave_de_impacto(): void
    {
        $produto = ProductEnrichmentService::enriquecerProduto(
            ['nome' => 'Chave de Impacto GDR120-LI 1/4 Pol. 12V Sem Bateria BOSCH-06019F0000-000'],
            'chave combinada bosch',
        );

        $this->assertSame('Bosch', $produto['marca_detectada']);
        $this->assertSame('chave de impacto', $produto['atributos_extraidos']['tipo']);
        $this->assertSame(0.0, $produto['score_produto']);
        $this->assertTrue($produto['correspondencia_fraca']);
    }

    public function test_chave_combinada_bosch_casa_com_chave_combinada(): void
    {
        $produto = ProductEnrichmentService::enriquecerProduto(
            ['nome' => 'Chave Combinada 13mm BOSCH'],
            'chave combinada bosch',
        );

        $this->assertSame('Bosch', $produto['marca_detectada']);
        $this->assertSame('chave combinada', $produto['atributos_extraidos']['tipo']);
        $this->assertFalse($produto['correspondencia_fraca']);
    }

    public function test_chave_grifo_nao_casa_com_outros_tipos_de_chave(): void
    {
        $produtos = ProductEnrichmentService::enriquecerProdutos([
            ['nome' => 'Chave Biela 7/16 3301521 Pol Red Gedore'],
            ['nome' => 'Chave Phillips 3/8 X 6 Pol 036.350 Gedore'],
            ['nome' => 'Chave Estrela Cr-V 18 x 19mm BELZER-301013B'],
            ['nome' => 'Chave Fixa 1.1/8 x 1.1/4 Pol 004.563 Gedore'],
        ], 'CHAVE GRIFO 18" GEDORE/BELZER/ROBUST');

        foreach ($produtos as $produto) {
            $this->assertSame(0.0, $produto['score_produto'], $produto['nome']);
            $this->assertTrue($produto['correspondencia_fraca'], $produto['nome']);
        }
    }

    public function test_chave_grifo_aceita_chave_tubo_ou_stilson(): void
    {
        $produtos = ProductEnrichmentService::enriquecerProdutos([
            ['nome' => 'Chave Para Tubos Modelo Americano de 18 Pol. GEDORE RED-R27160016'],
            ['nome' => 'Grifo / Chave Tubo Stilson 18" Gedore 225-18'],
        ], 'CHAVE GRIFO 18" GEDORE/BELZER/ROBUST');

        foreach ($produtos as $produto) {
            $this->assertSame('chave grifo', $produto['atributos_extraidos']['tipo'], $produto['nome']);
            $this->assertGreaterThanOrEqual(0.50, $produto['score_produto'], $produto['nome']);
            $this->assertFalse($produto['correspondencia_fraca'], $produto['nome']);
        }
    }

    public function test_filtrar_produtos_confiaveis_remove_correspondencias_fracas(): void
    {
        $produtos = ProductEnrichmentService::enriquecerProdutos([
            ['nome' => 'Alicate Universal BOSCH 8 Pol.'],
            ['nome' => 'Alicate Universal 08 Polegadas MTX-169069'],
            ['nome' => 'Alicate Universal TRAMONTINA 8 Pol.'],
            ['nome' => 'Alicate Universal GEDORE RED 8 Pol.'],
        ], 'alicate universal bosch');

        $filtrados = ProductEnrichmentService::filtrarProdutosConfiaveis($produtos);

        $this->assertCount(1, $filtrados);
        $this->assertSame('Bosch', $filtrados[0]['marca_detectada']);
    }
}
