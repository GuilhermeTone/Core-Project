<?php

namespace Tests\Unit\Scrapers;

use App\Services\Scrapers\RelevanceFilter;
use PHPUnit\Framework\TestCase;

class RelevanceFilterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function produto(string $nome, ?string $descricao = null, ?string $codigo = null, float $preco = 100.0): array
    {
        return [
            'nome'      => $nome,
            'descricao' => $descricao,
            'codigo'    => $codigo,
            'preco'     => $preco,
            'url'       => '#',
            'imagem'    => null,
        ];
    }

    // -------------------------------------------------------------------------
    // pontuar() — título
    // -------------------------------------------------------------------------

    public function test_pontuacao_maxima_para_match_exato(): void
    {
        $score = RelevanceFilter::pontuar(
            $this->produto('Alicate Universal'),
            ['alicate', 'universal'],
        );
        $this->assertEqualsWithDelta(1.0, $score, 0.01);
    }

    public function test_pontuacao_parcial_para_match_incompleto(): void
    {
        $score = RelevanceFilter::pontuar(
            $this->produto('Alicate de Bico'),
            ['alicate', 'universal'],
        );
        $this->assertEqualsWithDelta(0.5, $score, 0.01);
    }

    public function test_pontuacao_zero_para_sem_match(): void
    {
        $score = RelevanceFilter::pontuar(
            $this->produto('Kit Ferramentas'),
            ['alicate', 'universal'],
        );
        $this->assertEqualsWithDelta(0.0, $score, 0.01);
    }

    public function test_pontuacao_plural_equivale_singular(): void
    {
        $score = RelevanceFilter::pontuar(
            $this->produto('Alicates Universais'),
            ['alicate', 'universal'],
        );
        $this->assertGreaterThan(0.8, $score);
    }

    public function test_pontuacao_sem_acento_casa_com_acento(): void
    {
        $score = RelevanceFilter::pontuar(
            $this->produto('Parafusadeira à Bateria'),
            ['parafusadeira', 'bateria'],
        );
        $this->assertEqualsWithDelta(1.0, $score, 0.01);
    }

    public function test_pontuacao_sinonimo_philips_cruz(): void
    {
        $score = RelevanceFilter::pontuar(
            $this->produto('Chave Cruz Stanley'),
            ['chave', 'philips'],
        );
        $this->assertGreaterThan(0.8, $score);
    }

    // -------------------------------------------------------------------------
    // pontuar() — descrição
    // -------------------------------------------------------------------------

    public function test_pontuacao_via_descricao_quando_titulo_nao_casa(): void
    {
        // Título não contém "cromo", mas a descrição sim
        $score = RelevanceFilter::pontuar(
            $this->produto(
                nome: 'Alicate Universal 8"',
                descricao: 'Fabricado em aço cromo vanádio, ideal para uso profissional.',
            ),
            ['alicate', 'cromo'],
        );
        // "alicate" casa no título (score 0.5), "cromo" casa na descrição
        // desc_score = 2/2 * 0.75 = 0.75 → final = max(0.5, 0.75) = 0.75
        $this->assertGreaterThanOrEqual(0.5, $score);
    }

    public function test_descricao_sozinha_passa_threshold(): void
    {
        // Título sem relação, descrição contém os dois tokens
        $score = RelevanceFilter::pontuar(
            $this->produto(
                nome: 'Produto Genérico',
                descricao: 'Chave combinada em aço tratado para uso em parafusos combinados.',
            ),
            ['chave', 'combinada'],
        );
        // desc_score = 2/2 * 0.75 = 0.75 >= 0.5
        $this->assertGreaterThanOrEqual(0.5, $score);
    }

    // -------------------------------------------------------------------------
    // pontuar() — código do produto
    // -------------------------------------------------------------------------

    public function test_pontuacao_via_codigo_exato(): void
    {
        // Usuário buscou o código do produto
        $score = RelevanceFilter::pontuar(
            $this->produto(nome: 'Esmerilhadeira Angular 4.5"', codigo: 'ESM115'),
            ['esm115'],
        );
        $this->assertEqualsWithDelta(1.0, $score, 0.01);
    }

    public function test_pontuacao_via_codigo_com_fabricante(): void
    {
        // Código no formato "MARCA-REFERENCIA" (ex: Algolia model field)
        $score = RelevanceFilter::pontuar(
            $this->produto(nome: 'Alicate para Anéis', codigo: 'VONDER-3662004007'),
            ['3662004007'],
        );
        $this->assertEqualsWithDelta(1.0, $score, 0.01);
    }

    public function test_codigo_do_produto_nao_pontua_se_usuario_nao_busca_codigo(): void
    {
        $score = RelevanceFilter::pontuar(
            $this->produto(nome: 'Produto Genérico', codigo: 'VONDER-CHAVE-GRIFO-123456'),
            ['chave', 'grifo', 'vonder'],
        );

        $this->assertEqualsWithDelta(0.0, $score, 0.01);
    }

    public function test_codigo_nulo_nao_causa_erro(): void
    {
        $score = RelevanceFilter::pontuar(
            $this->produto('Chave Combinada', codigo: null),
            ['chave', 'combinada'],
        );
        $this->assertEqualsWithDelta(1.0, $score, 0.01);
    }

    // -------------------------------------------------------------------------
    // filtrar()
    // -------------------------------------------------------------------------

    public function test_filtrar_remove_irrelevantes(): void
    {
        $resultados = [
            $this->produto('Alicate Universal'),
            $this->produto('Kit Ferramentas Variadas'),
            $this->produto('Chave Combinada 12mm'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'chave combinada');

        $nomes = array_column($filtrados, 'nome');
        $this->assertContains('Chave Combinada 12mm', $nomes);
        $this->assertNotContains('Kit Ferramentas Variadas', $nomes);
    }

    public function test_filtrar_inclui_resultado_via_descricao(): void
    {
        $resultados = [
            $this->produto('Produto X', descricao: 'Chave combinada de alta resistência em aço.'),
            $this->produto('Alicate Universal'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'chave combinada');

        $nomes = array_column($filtrados, 'nome');
        $this->assertContains('Produto X', $nomes);
        $this->assertNotContains('Alicate Universal', $nomes);
    }

    public function test_filtrar_inclui_resultado_via_codigo(): void
    {
        $resultados = [
            $this->produto('Esmerilhadeira Angular 4.5"', codigo: 'ESM115'),
            $this->produto('Alicate Universal'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'ESM115');

        $nomes = array_column($filtrados, 'nome');
        $this->assertContains('Esmerilhadeira Angular 4.5"', $nomes);
        $this->assertNotContains('Alicate Universal', $nomes);
    }

    public function test_filtrar_planilha_com_medida_intervalo_e_codigo(): void
    {
        $resultados = [
            $this->produto(
                nome: 'Jogo de Chave Combinada 6mm a 32mm Gedore',
                codigo: 'GEDORE-002.603-1B-15M',
            ),
            $this->produto('Jogo Chave Allen 3mm a 14mm Tramontina'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'JOGO DE CHAVE COMBINADA 6MM A 32MM GEDORE 002.603 1B-15M');

        $this->assertCount(1, $filtrados);
        $this->assertSame('Jogo de Chave Combinada 6mm a 32mm Gedore', $filtrados[0]['nome']);
    }

    public function test_filtrar_ordena_por_relevancia(): void
    {
        $resultados = [
            $this->produto('Chave de Boca 12mm'),
            $this->produto('Chave Combinada 12mm'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'chave combinada');

        $this->assertSame('Chave Combinada 12mm', $filtrados[0]['nome']);
    }

    public function test_filtrar_retorna_tudo_se_sem_tokens(): void
    {
        $resultados = [
            $this->produto('Alicate Universal'),
            $this->produto('Chave Combinada'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'de do da');

        $this->assertCount(2, $filtrados);
    }

    public function test_filtrar_preserva_estrutura_do_resultado(): void
    {
        $original = $this->produto('Alicate Universal', 'Descrição', 'ALI-001', 49.90);
        $original['url'] = 'http://x';
        $original['imagem'] = 'img.jpg';

        $filtrados = RelevanceFilter::filtrar([$original], 'alicate universal');

        $this->assertSame($original, $filtrados[0]);
    }

    public function test_filtrar_vazio_retorna_vazio(): void
    {
        $this->assertSame([], RelevanceFilter::filtrar([], 'chave combinada'));
    }

    // -------------------------------------------------------------------------
    // filtrar() — kit/conjunto
    // -------------------------------------------------------------------------

    public function test_filtrar_remove_kit_quando_busca_nao_pede_kit(): void
    {
        $resultados = [
            $this->produto('Chave Combinada 13mm GEDORE'),
            $this->produto('Jogo de Chaves Combinadas 12 Peças'),
            $this->produto('Kit Chave Combinada Tramontina'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'chave combinada');
        $nomes = array_column($filtrados, 'nome');

        $this->assertContains('Chave Combinada 13mm GEDORE', $nomes);
        $this->assertNotContains('Jogo de Chaves Combinadas 12 Peças', $nomes);
        $this->assertNotContains('Kit Chave Combinada Tramontina', $nomes);
    }

    public function test_filtrar_mantem_kit_quando_busca_pede_kit(): void
    {
        $resultados = [
            $this->produto('Jogo de Chaves Combinadas 12 Peças Tramontina'),
            $this->produto('Kit de Chaves Combinadas Stanley'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'jogo chaves combinadas');
        $nomes = array_column($filtrados, 'nome');

        $this->assertContains('Jogo de Chaves Combinadas 12 Peças Tramontina', $nomes);
    }

    public function test_filtrar_mantem_kit_quando_busca_contem_conjunto(): void
    {
        $resultados = [
            $this->produto('Conjunto de Chaves Combinadas 17 Peças'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'conjunto chaves combinadas');
        $this->assertCount(1, $filtrados);
    }
}
