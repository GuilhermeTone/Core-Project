<?php

namespace App\Services\Scrapers;

class FerramentasCuritibaScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.ferramentascuritiba.com.br';

    public function identificador(): string
    {
        return 'ferramentascuritiba';
    }

    public function nomeSite(): string
    {
        return 'Ferramentas Curitiba';
    }

    protected function executarBusca(string $termo): array
    {
        $url = self::BASE_URL . '/busca?q=' . urlencode($termo);
        $html = $this->get($url);

        if (empty($html)) {
            return [];
        }

        $array = $this->extrairArrayJsApos($html, '"impressions":');
        $itens = $array ? json_decode($array, true) : null;

        if (!is_array($itens)) {
            return [];
        }

        $resultados = [];

        foreach ($itens as $item) {
            if (empty($item['name'])) {
                continue;
            }

            $resultados[] = [
                'nome'      => html_entity_decode((string) $item['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'descricao' => $item['brand'] ?? null,
                'preco'     => isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null,
                'url'       => $url,
                'imagem'    => null,
                'codigo'    => $item['sku'] ?? ($item['id'] ?? null),
            ];
        }

        return array_slice($resultados, 0, 10);
    }
}
