<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class GraviaScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.gravia.com';

    public function identificador(): string
    {
        return 'gravia';
    }

    public function nomeSite(): string
    {
        return 'Gravia';
    }

    protected function executarBusca(string $termo): array
    {
        $html = $this->get(self::BASE_URL . '/busca?ft=' . urlencode($termo));

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $resultados = [];

        try {
            $crawler->filter('.vitrine li')->each(function (Crawler $node) use (&$resultados) {
                $nome = $this->textoPrimeiro($node, ['.product-name a']);
                $href = $this->atributoPrimeiro($node, ['.product-name a', '.product-image a'], 'href');

                if (empty($nome) || empty($href)) {
                    return;
                }

                $resultados[] = [
                    'nome'      => $nome,
                    'descricao' => null,
                    'preco'     => $this->precoPorTexto($this->textoPrimeiro($node, ['.price-best'])),
                    'url'       => $this->urlAbsoluta(self::BASE_URL, $href),
                    'imagem'    => $this->atributoPrimeiro($node, ['.product-image img', 'img'], 'src'),
                    'codigo'    => $node->filter('.ct')->count() ? $node->filter('.ct')->first()->attr('data-sku') : null,
                ];
            });
        } catch (\Exception $e) {
        }

        return array_slice($resultados, 0, 10);
    }
}
