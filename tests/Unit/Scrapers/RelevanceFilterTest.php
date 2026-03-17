<?php

namespace Tests\Unit\Scrapers;

use App\Services\Scrapers\RelevanceFilter;
use PHPUnit\Framework\TestCase;

class RelevanceFilterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function produto(string $nome, float $preco = 100.0): array
    {
        return ['nome' => $nome, 'descricao' => null, 'preco' => $preco, 'url' => '#', 'imagem' => null];
    }

    // -------------------------------------------------------------------------
    // pontuar()
    // -------------------------------------------------------------------------

    public function test_pontuacao_maxima_para_match_exato(): void
    {
        $score = RelevanceFilter::pontuar('Alicate Universal', ['alicate', 'universal']);
        $this->assertEqualsWithDelta(1.0, $score, 0.01);
    }

    public function test_pontuacao_parcial_para_match_incompleto(): void
    {
        // "alicate" encontrado, "universal" não → 1/2 = 0.5
        $score = RelevanceFilter::pontuar('Alicate de Bico', ['alicate', 'universal']);
        $this->assertEqualsWithDelta(0.5, $score, 0.01);
    }

    public function test_pontuacao_zero_para_sem_match(): void
    {
        $score = RelevanceFilter::pontuar('Kit Ferramentas', ['alicate', 'universal']);
        $this->assertEqualsWithDelta(0.0, $score, 0.01);
    }

    public function test_pontuacao_plural_equivale_singular(): void
    {
        // query: "alicate", resultado: "alicates" — deve casar
        $score = RelevanceFilter::pontuar('Alicates Universais', ['alicate', 'universal']);
        $this->assertGreaterThan(0.8, $score);
    }

    public function test_pontuacao_sem_acento_casa_com_acento(): void
    {
        $score = RelevanceFilter::pontuar('Parafusadeira à Bateria', ['parafusadeira', 'bateria']);
        $this->assertEqualsWithDelta(1.0, $score, 0.01);
    }

    public function test_pontuacao_sinonimo_philips_cruz(): void
    {
        // query "chave philips" deve casar com resultado "Chave Cruz"
        $score = RelevanceFilter::pontuar('Chave Cruz Stanley', ['chave', 'philips']);
        $this->assertGreaterThan(0.8, $score);
    }

    // -------------------------------------------------------------------------
    // filtrar()
    // -------------------------------------------------------------------------

    public function test_filtrar_remove_irrelevantes(): void
    {
        $resultados = [
            $this->produto('Alicate Universal'),
            $this->produto('Kit Ferramentas Variadas'),  // sem relação com "chave combinada"
            $this->produto('Chave Combinada 12mm'),
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'chave combinada');

        $nomes = array_column($filtrados, 'nome');
        $this->assertContains('Chave Combinada 12mm', $nomes);
        $this->assertNotContains('Kit Ferramentas Variadas', $nomes);
    }

    public function test_filtrar_ordena_por_relevancia(): void
    {
        $resultados = [
            $this->produto('Chave de Boca 12mm'),        // parcial
            $this->produto('Chave Combinada 12mm'),      // completo
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

        // Termo só de stopwords → sem tokens → retorna tudo
        $filtrados = RelevanceFilter::filtrar($resultados, 'de do da');

        $this->assertCount(2, $filtrados);
    }

    public function test_filtrar_preserva_estrutura_do_resultado(): void
    {
        $resultados = [
            ['nome' => 'Alicate Universal', 'descricao' => 'Desc', 'preco' => 49.90, 'url' => 'http://x', 'imagem' => 'img.jpg'],
        ];

        $filtrados = RelevanceFilter::filtrar($resultados, 'alicate universal');

        $this->assertSame($resultados[0], $filtrados[0]);
    }

    public function test_filtrar_vazio_retorna_vazio(): void
    {
        $this->assertSame([], RelevanceFilter::filtrar([], 'chave combinada'));
    }
}
