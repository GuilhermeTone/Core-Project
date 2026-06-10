<?php

namespace Tests\Unit\Scrapers;

use App\Services\Scrapers\BaseScraper;
use PHPUnit\Framework\TestCase;

class DisponibilidadeScraperTest extends TestCase
{
    public function test_busca_remove_produto_explicitamente_fora_de_estoque(): void
    {
        $scraper = new class extends BaseScraper {
            public function identificador(): string
            {
                return 'teste';
            }

            public function nomeSite(): string
            {
                return 'Teste';
            }

            protected function executarBusca(string $termo): array
            {
                return [
                    [
                        'nome' => 'Chave Grifo Gedore 18 Pol',
                        'descricao' => null,
                        'preco' => 100.0,
                        'url' => 'https://example.com/sem-estoque',
                        'imagem' => null,
                        'codigo' => null,
                        'disponivel' => false,
                    ],
                    [
                        'nome' => 'Chave Grifo Gedore 18 Pol',
                        'descricao' => null,
                        'preco' => 120.0,
                        'url' => 'https://example.com/disponivel',
                        'imagem' => null,
                        'codigo' => null,
                        'disponivel' => true,
                    ],
                ];
            }
        };

        $resultados = $scraper->buscar('chave grifo gedore 18 pol');

        $this->assertCount(1, $resultados);
        $this->assertSame('https://example.com/disponivel', $resultados[0]['url']);
    }

    public function test_textos_de_indisponibilidade_sao_reconhecidos(): void
    {
        $scraper = new class extends BaseScraper {
            public function identificador(): string
            {
                return 'teste';
            }

            public function nomeSite(): string
            {
                return 'Teste';
            }

            public function disponivelPorTexto(string $texto): bool
            {
                return $this->disponibilidadePorTexto($texto);
            }

            protected function executarBusca(string $termo): array
            {
                return [];
            }
        };

        $this->assertFalse($scraper->disponivelPorTexto('FORA DE ESTOQUE Produto sem estoque. Avise-me quando chegar'));
        $this->assertFalse($scraper->disponivelPorTexto('Produto indisponível'));
        $this->assertTrue($scraper->disponivelPorTexto('Adicionar ao carrinho'));
    }
}
