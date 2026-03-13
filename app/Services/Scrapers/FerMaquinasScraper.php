<?php

namespace App\Services\Scrapers;

class FerMaquinasScraper extends BaseScraper
{
    public function identificador(): string
    {
        return 'fermaquinas';
    }

    public function nomeSite(): string
    {
        return 'Fermáquinas';
    }

    public function buscar(string $termo): array
    {
        $query = rawurlencode($termo);

        // VTEX Catalog search API
        $url  = "https://www.fermaquinas.com.br/api/catalog_system/pub/products/search/{$query}?_from=0&_to=9";
        $html = $this->get($url);

        $data = json_decode($html, true);

        if (!is_array($data) || empty($data)) {
            return [];
        }

        $resultados = [];

        foreach ($data as $product) {
            $nome = $product['productName'] ?? null;
            $link = $product['link']        ?? null;

            if (empty($nome) || empty($link)) {
                continue;
            }

            $preco  = null;
            $imagem = null;

            $item = $product['items'][0] ?? null;
            if ($item) {
                $preco  = (float) ($item['sellers'][0]['commertialOffer']['Price'] ?? 0) ?: null;
                $imagem = $item['images'][0]['imageUrl'] ?? null;
            }

            $descricao = null;
            if (!empty($product['description'])) {
                $descricao = mb_substr(strip_tags($product['description']), 0, 300);
            }

            $resultados[] = [
                'nome'      => $nome,
                'descricao' => $descricao,
                'preco'     => $preco,
                'url'       => $link,
                'imagem'    => $imagem,
            ];
        }

        return $resultados;
    }
}
