<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class MinasFerramentasScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.minasferramentas.com.br';

    public function identificador(): string
    {
        return 'minasferramentas';
    }

    public function nomeSite(): string
    {
        return 'Minas Ferramentas';
    }

    protected function executarBusca(string $termo): array
    {
        $html = $this->get(self::BASE_URL . '/busca?q=' . urlencode($termo));

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $resultados = [];

        try {
            $crawler->filter('.card-produto')->each(function (Crawler $node) use (&$resultados) {
                $nome = $this->textoPrimeiro($node, ['.title', 'img[alt]']);
                $href = $this->atributoPrimeiro($node, ['a[href]'], 'href');

                if (empty($nome) || empty($href)) {
                    return;
                }

                $preco = $this->precoPorTexto($this->textoPrimeiro($node, ['.preco-por']))
                    ?? $this->precoPorTexto($this->textoPrimeiro($node, ['.avista .preco-de', '.preco-de']));

                $resultados[] = [
                    'nome'      => $nome,
                    'descricao' => null,
                    'preco'     => $preco,
                    'url'       => $this->urlAbsoluta(self::BASE_URL, $href),
                    'imagem'    => $this->atributoPrimeiro($node, ['img'], 'data-src')
                        ?? $this->atributoPrimeiro($node, ['img'], 'src'),
                    'codigo'    => null,
                ];
            });
        } catch (\Exception $e) {
        }

        return array_slice($resultados, 0, 10);
    }
}
