<?php

namespace App\Services\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

class TramontinaScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.tramontina.com.br';

    public function identificador(): string
    {
        return 'tramontinaoficial';
    }

    public function nomeSite(): string
    {
        return 'Tramontina Loja Oficial';
    }

    protected function executarBusca(string $termo): array
    {
        $html = $this->get(self::BASE_URL . '/busca/?q=' . urlencode($termo));

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $resultados = [];

        try {
            $crawler->filter('.tr-productTile')->each(function (Crawler $node) use (&$resultados) {
                $payload = $node->attr('data-cbt');
                $item = null;

                if (!empty($payload)) {
                    $json = html_entity_decode($payload, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $data = json_decode($json, true);
                    $item = $data['ecommerce']['items'][0] ?? null;
                }

                $nome = $item['item_name'] ?? $this->textoPrimeiro($node, ['a[title]', '.tr-productTile__name']);
                $href = $this->atributoPrimeiro($node, ['a[href]'], 'href');

                if (empty($nome)) {
                    return;
                }

                $resultados[] = [
                    'nome'      => html_entity_decode((string) $nome, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'descricao' => $item['item_brand'] ?? 'Tramontina',
                    'preco'     => isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null,
                    'url'       => $this->urlAbsoluta(self::BASE_URL, $href),
                    'imagem'    => $this->atributoPrimeiro($node, ['img'], 'src')
                        ?? $this->atributoPrimeiro($node, ['img'], 'data-src'),
                    'codigo'    => $item['item_id'] ?? null,
                    'disponivel' => $this->disponibilidadePorCampos($item ?? []) ?? $this->disponibilidadePorNode($node),
                ];
            });
        } catch (\Exception $e) {
        }

        return array_slice($resultados, 0, 10);
    }
}
