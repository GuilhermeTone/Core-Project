<?php

namespace App\Services\Scrapers;

class KennedyScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.ferramentaskennedy.com.br';
    private const CDN_URL  = 'https://cdn.ferramentaskennedy.com.br/storage/kennedy/';

    public function identificador(): string
    {
        return 'kennedy';
    }

    public function nomeSite(): string
    {
        return 'Ferramentas Kennedy';
    }

    protected function executarBusca(string $termo): array
    {
        $query = urlencode($termo);
        $url   = self::BASE_URL . "/busca?q={$query}";

        $html = $this->get($url);
        if (empty($html)) {
            return [];
        }

        // Kennedy uses Inertia.js: all page data is in data-page attribute of #app
        if (!preg_match('/<div\s[^>]*id="app"[^>]*data-page="([^"]+)"/', $html, $match)) {
            return [];
        }

        $pageData = json_decode(html_entity_decode($match[1]), true);
        if (!is_array($pageData)) {
            return [];
        }

        $products = $pageData['props']['data'] ?? [];
        if (empty($products)) {
            return [];
        }

        $resultados = [];

        foreach ($products as $product) {
            $nome = $product['nome'] ?? null;
            $slug = $product['slug'] ?? null;

            if (empty($nome) || empty($slug)) {
                continue;
            }

            $url = self::BASE_URL . '/' . ltrim($slug, '/');

            // preco_por é o preço de venda; preco_de é o original
            $preco = null;
            foreach (['preco_por', 'preco_de', 'valor'] as $field) {
                if (!empty($product[$field]) && (float) $product[$field] > 0) {
                    $preco = (float) $product[$field];
                    break;
                }
            }

            // Imagem: CDN + filename da imagem principal
            $imagem = null;
            if (!empty($product['imagem'])) {
                $imagem = self::CDN_URL . $product['imagem'];
            } elseif (!empty($product['imagens'][0]['url'])) {
                $imagem = self::CDN_URL . $product['imagens'][0]['url'];
            }

            $descricao = null;
            if (!empty($product['descricao'])) {
                $descricao = mb_substr(strip_tags($product['descricao']), 0, 300);
            }

            // Tenta campos comuns de código de produto em backends Laravel/Inertia
            $codigo = $product['codigo'] ?? $product['referencia'] ?? $product['sku'] ?? null;

            $resultados[] = [
                'nome'      => $nome,
                'descricao' => $descricao,
                'preco'     => $preco,
                'url'       => $url,
                'imagem'    => $imagem,
                'codigo'    => $codigo,
                'disponivel' => $this->disponibilidadePorCampos($product) ?? $this->disponibilidadePorTexto(json_encode($product, JSON_UNESCAPED_UNICODE)),
            ];

            if (count($resultados) >= 10) {
                break;
            }
        }

        return $resultados;
    }
}
