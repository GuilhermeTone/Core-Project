<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class MercadoLivreScraper extends BaseScraper
{
    public function identificador(): string
    {
        return 'mercadolivre';
    }

    public function nomeSite(): string
    {
        return 'Mercado Livre';
    }

    public function buscar(string $termo): array
    {
        $query = urlencode($termo);
        $url = "https://lista.mercadolivre.com.br/{$query}";

        $html = $this->get($url);
        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $resultados = [];

        try {
            $crawler->filter('.ui-search-layout__item')->each(function (Crawler $node) use (&$resultados) {
                try {
                    $nome = '';
                    $linkNode = null;

                    // Try multiple selectors for title
                    foreach (['.poly-component__title', 'h2.ui-search-item__title', '.ui-search-item__title'] as $sel) {
                        try {
                            $nome = trim($node->filter($sel)->first()->text());
                            break;
                        } catch (\Exception $e) {
                        }
                    }

                    if (empty($nome)) {
                        return;
                    }

                    // Get link
                    $href = '';
                    foreach (['a.poly-component__title', 'a.ui-search-link', 'a[href]'] as $sel) {
                        try {
                            $href = $node->filter($sel)->first()->attr('href');
                            break;
                        } catch (\Exception $e) {
                        }
                    }

                    if (empty($href)) {
                        return;
                    }

                    // Get price
                    $preco = null;
                    try {
                        $inteiro = $node->filter('.andes-money-amount__fraction')->first()->text();
                        $centavos = '';
                        try {
                            $centavos = $node->filter('.andes-money-amount__cents')->first()->text();
                        } catch (\Exception $e) {
                        }
                        $precoStr = preg_replace('/\D/', '', $inteiro);
                        if (!empty($precoStr)) {
                            $preco = (float) ($precoStr . '.' . (empty($centavos) ? '00' : str_pad(preg_replace('/\D/', '', $centavos), 2, '0')));
                        }
                    } catch (\Exception $e) {
                    }

                    // Get image — ML usa src direto (não data-src)
                    $imagem = null;
                    try {
                        $img = $node->filter('img.poly-component__picture')->first();
                        $imagem = $img->attr('src') ?? $img->attr('data-src');
                    } catch (\Exception $e) {
                    }

                    $resultados[] = [
                        'nome'      => $nome,
                        'descricao' => null,
                        'preco'     => $preco,
                        'url'       => $this->limparUrlML($href),
                        'imagem'    => $imagem,
                    ];
                } catch (\Exception $e) {
                }
            });
        } catch (\Exception $e) {
        }

        return array_slice($resultados, 0, 10);
    }

    /**
     * ML às vezes retorna URLs diretas (mercadolivre.com.br) com fragment #tracking
     * ou URLs de click tracking (click1.mercadolivre.com.br?a=ENCRYPTED).
     * Em ambos os casos extrai a URL limpa e navegável do produto.
     */
    private function limparUrlML(string $href): string
    {
        // URL direta: https://www.mercadolivre.com.br/produto/p/MLB123#tracking_params
        if (str_contains($href, 'mercadolivre.com.br') && str_contains($href, '#')) {
            return strtok($href, '#');
        }

        // URL de click tracking: tenta extrair o destino do parâmetro "destUrl" ou "url"
        if (str_contains($href, 'click1.mercadolivre') || str_contains($href, 'mclics')) {
            parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $params);

            foreach (['destUrl', 'url', 'redirect'] as $key) {
                if (!empty($params[$key])) {
                    return urldecode($params[$key]);
                }
            }

            // Se não achou parâmetro de destino, devolve o href original
            // (ainda funciona: redireciona para o produto ao clicar)
            return $href;
        }

        return $href;
    }
}
