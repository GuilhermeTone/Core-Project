<?php

namespace Tests\Unit\Scrapers;

use App\Services\Scrapers\QueryNormalizer;
use PHPUnit\Framework\TestCase;

class QueryNormalizerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // limpar()
    // -------------------------------------------------------------------------

    public function test_limpar_remove_espacos_extras(): void
    {
        $this->assertSame('chave combinada', QueryNormalizer::limpar('  chave   combinada  '));
    }

    public function test_limpar_preserva_acentos(): void
    {
        $this->assertSame('Chave Combinação', QueryNormalizer::limpar('Chave Combinação'));
    }

    // -------------------------------------------------------------------------
    // tokenizar()
    // -------------------------------------------------------------------------

    public function test_tokenizar_lowercase_e_sem_acentos(): void
    {
        $this->assertSame(['chave', 'combinada'], QueryNormalizer::tokenizar('Chave Combinada'));
    }

    public function test_tokenizar_remove_stopwords(): void
    {
        $tokens = QueryNormalizer::tokenizar('chave de fenda');
        $this->assertNotContains('de', $tokens);
        $this->assertContains('chave', $tokens);
        $this->assertContains('fenda', $tokens);
    }

    public function test_tokenizar_remove_acentos(): void
    {
        $tokens = QueryNormalizer::tokenizar('parafusadeira à bateria');
        $this->assertContains('parafusadeira', $tokens);
        $this->assertContains('bateria', $tokens);
        $this->assertNotContains('à', $tokens);
    }

    public function test_tokenizar_deduplica(): void
    {
        $tokens = QueryNormalizer::tokenizar('alicate alicate universal');
        $this->assertSame(['alicate', 'universal'], $tokens);
    }

    public function test_tokenizar_ignora_tokens_muito_curtos(): void
    {
        $tokens = QueryNormalizer::tokenizar('chave 8 mm');
        $this->assertNotContains('8', $tokens);
        $this->assertContains('chave', $tokens);
        $this->assertContains('8mm', $tokens);
    }

    public function test_tokenizar_preserva_fracoes_medidas_e_codigos(): void
    {
        $tokens = QueryNormalizer::tokenizar('CHAVE PHILIPS 1/4" X 4" GEDORE-015.644');

        $this->assertContains('1/4', $tokens);
        $this->assertContains('4pol', $tokens);
        $this->assertContains('gedore015644', $tokens);
    }

    public function test_tokenizar_preserva_intervalo_de_medidas(): void
    {
        $tokens = QueryNormalizer::tokenizar('JOGO CHAVE COMBINADA 6MM A 32MM');

        $this->assertContains('6mm', $tokens);
        $this->assertContains('32mm', $tokens);
    }

    public function test_tokenizar_expande_intervalo_com_hifen_e_unidade_no_final(): void
    {
        $tokens = QueryNormalizer::tokenizar('Jogo Chave Estrela 6-32mm');

        $this->assertContains('6mm', $tokens);
        $this->assertContains('32mm', $tokens);
        $this->assertNotContains('632mm', $tokens);
    }

    // -------------------------------------------------------------------------
    // removerAcentos()
    // -------------------------------------------------------------------------

    public function test_remover_acentos_completo(): void
    {
        $this->assertSame('acoes de sao joao', QueryNormalizer::removerAcentos('ações de são joão'));
    }

    // -------------------------------------------------------------------------
    // sinonimos()
    // -------------------------------------------------------------------------

    public function test_sinonimos_retorna_o_proprio_token(): void
    {
        $s = QueryNormalizer::sinonimos('alicate');
        $this->assertContains('alicate', $s);
    }

    public function test_sinonimos_philips_inclui_cruz(): void
    {
        $s = QueryNormalizer::sinonimos('philips');
        $this->assertContains('cruz', $s);
        $this->assertContains('estrela', $s);
    }

    public function test_sinonimos_fenda_inclui_plana(): void
    {
        $s = QueryNormalizer::sinonimos('fenda');
        $this->assertContains('plana', $s);
    }

    public function test_sinonimos_por_valor_retorna_canonical(): void
    {
        // 'cruz' é sinônimo de 'philips', então sinonimos('cruz') deve incluir 'philips'
        $s = QueryNormalizer::sinonimos('cruz');
        $this->assertContains('philips', $s);
    }
}
