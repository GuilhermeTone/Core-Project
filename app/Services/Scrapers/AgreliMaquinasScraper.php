<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class AgreliMaquinasScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.agrelimaquinas.com.br';

    public function identificador(): string
    {
        return 'agrelimaquinas';
    }

    public function nomeSite(): string
    {
        return 'Agreli Máquinas';
    }

    protected function executarBusca(string $termo): array
    {
        $url = self::BASE_URL . '/loja/busca.php?loja=1004808&palavra_busca=' . urlencode($termo);
        $html = $this->get($url);

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $resultados = [];

        try {
            $crawler->filter('li.item .product')->each(function (Crawler $node) use (&$resultados) {
                $nome = $this->atributoPrimeiro($node, ['[data-ga4-name]'], 'data-ga4-name')
                    ?? $this->textoPrimeiro($node, ['.product-name']);
                $href = $this->atributoPrimeiro($node, ['a.product-info', '.space-image', 'a[href]'], 'href');

                if (empty($nome) || empty($href)) {
                    return;
                }

                $preco = null;
                $gaPrice = $this->atributoPrimeiro($node, ['[data-ga4-price]'], 'data-ga4-price');
                if (is_numeric($gaPrice) && (float) $gaPrice > 0) {
                    $preco = (float) $gaPrice;
                }

                if ($preco === null) {
                    $preco = $this->precoPorTexto($this->textoPrimeiro($node, ['.current-price']));
                }

                $resultados[] = [
                    'nome'      => trim($nome),
                    'descricao' => $this->atributoPrimeiro($node, ['[data-ga4-brand]'], 'data-ga4-brand'),
                    'preco'     => $preco,
                    'url'       => $this->urlAbsoluta(self::BASE_URL, $href),
                    'imagem'    => $this->atributoPrimeiro($node, ['img'], 'data-src')
                        ?? $this->atributoPrimeiro($node, ['img'], 'src'),
                    'codigo'    => $node->attr('product-ref'),
                    'disponivel' => $this->disponibilidadePorNode($node),
                ];
            });
        } catch (\Exception $e) {
        }

        return array_slice($resultados, 0, 10);
    }
}
