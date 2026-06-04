<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class MartineliScraper extends BaseScraper
{
    public function identificador(): string
    {
        return 'martineli';
    }

    public function nomeSite(): string
    {
        return 'Martineli Ferramentas';
    }

    protected function executarBusca(string $termo): array
    {
        $query = urlencode($termo);

        // OpenCart search URL
        $url  = "https://www.martineliferramentas.com.br/index.php?route=product/search&search={$query}";
        $html = $this->get($url);

        if (empty($html)) {
            return [];
        }

        $crawler    = new Crawler($html);
        $resultados = [];

        $containerSelectors = [
            '.product-layout',
            '.product-thumb',
            '.product-grid .product',
        ];

        foreach ($containerSelectors as $containerSel) {
            try {
                $nodes = $crawler->filter($containerSel);
                if ($nodes->count() === 0) {
                    continue;
                }

                $nodes->each(function (Crawler $node) use (&$resultados) {
                    $nome = '';
                    foreach (['.product-name a', 'h4 a', 'h3 a', '.name a', 'a[title]'] as $sel) {
                        try {
                            $nome = trim($node->filter($sel)->first()->text());
                            if (!empty($nome)) {
                                break;
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    if (empty($nome)) {
                        return;
                    }

                    $href = '';
                    foreach (['.product-name a', 'h4 a', 'h3 a', '.name a', 'a'] as $sel) {
                        try {
                            $href = $node->filter($sel)->first()->attr('href');
                            if (!empty($href)) {
                                break;
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    if (empty($href)) {
                        return;
                    }

                    // Martineli shows Pix/boleto discount in .price-new and the
                    // regular card price in .price-old. Quotations use card/regular price.
                    $preco = $this->precoPorTexto($this->textoPrimeiro($node, ['.price-old']))
                        ?? $this->precoPorTexto($this->textoPrimeiro($node, ['.price-new', '.product-price-new', '.special-price', '.price']));

                    $imagem = null;
                    try {
                        $img    = $node->filter('img')->first();
                        $imagem = $img->attr('src') ?? $img->attr('data-src') ?? $img->attr('data-lazy');
                    } catch (\Exception $e) {
                    }

                    // OpenCart lista curta descrição no card — filtra placeholder ".."
                    $descricao = null;
                    try {
                        $txt = trim($node->filter('.description')->first()->text());
                        if (strlen($txt) > 5) {
                            $descricao = mb_substr(strip_tags($txt), 0, 300);
                        }
                    } catch (\Exception $e) {
                    }

                    $resultados[] = [
                        'nome'      => $nome,
                        'descricao' => $descricao,
                        'preco'     => $preco,
                        'url'       => $href,
                        'imagem'    => $imagem,
                        'codigo'    => null,
                    ];
                });

                if (!empty($resultados)) {
                    break;
                }
            } catch (\Exception $e) {
            }
        }

        return array_slice($resultados, 0, 10);
    }
}
