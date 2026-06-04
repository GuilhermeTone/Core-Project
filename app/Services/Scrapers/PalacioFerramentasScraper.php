<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class PalacioFerramentasScraper extends BaseScraper
{
    private const BASE_URL = 'https://palaciodasferramentas.com.br';

    public function identificador(): string
    {
        return 'palaciodasferramentas';
    }

    public function nomeSite(): string
    {
        return 'Palácio das Ferramentas';
    }

    protected function executarBusca(string $termo): array
    {
        $html = $this->get(self::BASE_URL . '/catalogsearch/result/?q=' . urlencode($termo));

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $resultados = [];

        try {
            $crawler->filter('.product-item-info')->each(function (Crawler $node) use (&$resultados) {
                $nome = $this->textoPrimeiro($node, ['.product-item-link']);
                $href = $this->atributoPrimeiro($node, ['.product-item-link', '.product-item-photo'], 'href');

                if (empty($nome) || empty($href)) {
                    return;
                }

                $preco = $this->precoPorTexto($this->textoPrimeiro($node, ['.price strong', '.price']));

                $resultados[] = [
                    'nome'      => $nome,
                    'descricao' => null,
                    'preco'     => $preco,
                    'url'       => $this->urlAbsoluta(self::BASE_URL, $href),
                    'imagem'    => $this->atributoPrimeiro($node, ['img.product-image-photo', 'img'], 'src'),
                    'codigo'    => $this->atributoPrimeiro($node, ['form[data-product-sku]'], 'data-product-sku'),
                ];
            });
        } catch (\Exception $e) {
        }

        return array_slice($resultados, 0, 10);
    }
}
