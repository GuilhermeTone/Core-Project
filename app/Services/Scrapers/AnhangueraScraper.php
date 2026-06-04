<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class AnhangueraScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.anhangueraferramentas.com.br';

    public function identificador(): string
    {
        return 'anhanguera';
    }

    public function nomeSite(): string
    {
        return 'Anhanguera Ferramentas';
    }

    protected function executarBusca(string $termo): array
    {
        $query = urlencode($termo);
        $url   = self::BASE_URL . "/busca?busca={$query}";

        $html = $this->get($url);
        if (empty($html)) {
            return [];
        }

        $crawler    = new Crawler($html);
        $resultados = [];

        try {
            $crawler->filter('.spot-wrapper')->each(function (Crawler $node) use (&$resultados) {
                // Nome e link
                try {
                    $anchor = $node->filter('.spot-title a')->first();
                    $nome   = trim($anchor->text());
                    $href   = $anchor->attr('href');
                } catch (\Exception $e) {
                    return;
                }

                if (empty($nome) || empty($href)) {
                    return;
                }

                if (!str_starts_with($href, 'http')) {
                    $href = self::BASE_URL . $href;
                }

                // Preço — texto: "por R$ 357,96 à vista no PIX..."
                $preco = null;
                try {
                    $precoText = $node->filter('.product-price_value')->first()->text();
                    if (preg_match('/R\$\s*([\d]+(?:[.,][\d]+)*)/', $precoText, $m)) {
                        $valor = str_replace('.', '', $m[1]);
                        $valor = str_replace(',', '.', $valor);
                        $preco = (float) $valor ?: null;
                    }
                } catch (\Exception $e) {
                }

                // Imagem — primeira img com classe "primary" dentro do spot-image_box
                $imagem = null;
                try {
                    $img    = $node->filter('.spot-image_box img.primary')->first();
                    $imagem = $img->attr('src');
                } catch (\Exception $e) {
                    try {
                        $img    = $node->filter('.spot-image_box img')->first();
                        $imagem = $img->attr('src') ?? $img->attr('data-src');
                    } catch (\Exception $e2) {
                    }
                }

                $resultados[] = [
                    'nome'      => $nome,
                    'descricao' => null,
                    'preco'     => $preco,
                    'url'       => $href,
                    'imagem'    => $imagem,
                    'codigo'    => null,
                ];
            });
        } catch (\Exception $e) {
        }

        return array_slice($resultados, 0, 10);
    }
}
