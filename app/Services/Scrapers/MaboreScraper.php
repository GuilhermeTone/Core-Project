<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class MaboreScraper extends BaseScraper
{
    public function identificador(): string
    {
        return 'mabore';
    }

    public function nomeSite(): string
    {
        return 'Mabore Ferramentas';
    }

    protected function executarBusca(string $termo): array
    {
        $query = urlencode($termo);

        // Parâmetro correto da plataforma Irroba: search= (não q=)
        $url  = "https://www.mabore.com.br/busca?search={$query}";
        $html = $this->get($url);

        if (empty($html)) {
            return [];
        }

        $crawler    = new Crawler($html);
        $resultados = [];

        try {
            $crawler->filter('.product.product-grid')->each(function (Crawler $node) use (&$resultados) {
                // Nome e link
                $nome = '';
                $href = '';
                try {
                    $anchor = $node->filter('h3.product-title a')->first();
                    $nome   = trim($anchor->text());
                    $href   = $anchor->attr('href');
                } catch (\Exception $e) {
                    return;
                }

                if (empty($nome) || empty($href)) {
                    return;
                }

                // Preço: usa o preço com desconto se existir, senão preço normal
                $preco = null;
                foreach (['.product-price-new', '.product-price-old', '.product-price'] as $sel) {
                    try {
                        $txt   = $node->filter($sel)->first()->text();
                        $clean = preg_replace('/[^\d,]/', '', $txt);
                        $clean = str_replace(',', '.', $clean);
                        if (is_numeric($clean) && (float) $clean > 0) {
                            $preco = (float) $clean;
                            break;
                        }
                    } catch (\Exception $e) {
                    }
                }

                // Imagem
                $imagem = null;
                try {
                    $img    = $node->filter('.product-image img')->first();
                    $imagem = $img->attr('src') ?? $img->attr('data-src');
                } catch (\Exception $e) {
                }

                $resultados[] = [
                    'nome'      => $nome,
                    'descricao' => null,
                    'preco'     => $preco,
                    'url'       => $href,
                    'imagem'    => $imagem,
                ];
            });
        } catch (\Exception $e) {
        }

        return array_slice($resultados, 0, 10);
    }
}
