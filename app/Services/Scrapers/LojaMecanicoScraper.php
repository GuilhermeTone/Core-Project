<?php

namespace App\Services\Scrapers;

class LojaMecanicoScraper extends BaseScraper
{
    // Credenciais públicas de busca (search-only key embutida no JS do site)
    private const ALGOLIA_APP_ID  = 'VSCMPGMHA0';
    private const ALGOLIA_API_KEY = '2484ae1c3aa41bc1999ab288ce8ac63d';
    private const ALGOLIA_INDEX   = 'ldm_products';
    private const IMAGE_CDN       = 'https://img.lojadomecanico.com.br/IMAGENS';

    public function identificador(): string
    {
        return 'lojadomecanico';
    }

    public function nomeSite(): string
    {
        return 'Loja do Mecânico';
    }

    protected function executarBusca(string $termo): array
    {
        $url = sprintf(
            'https://%s-dsn.algolia.net/1/indexes/%s/query',
            self::ALGOLIA_APP_ID,
            self::ALGOLIA_INDEX
        );

        $body = json_encode([
            'query'                => $termo,
            'hitsPerPage'          => 10,
            'attributesToRetrieve' => ['title', 'description', 'price', 'url', 'images', 'available'],
            'filters'              => 'available:true',
        ]);

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'X-Algolia-Application-Id' => self::ALGOLIA_APP_ID,
                    'X-Algolia-API-Key'        => self::ALGOLIA_API_KEY,
                    'Content-Type'             => 'application/json',
                ],
                'body' => $body,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return [];
        }

        if (empty($data['hits'])) {
            return [];
        }

        $resultados = [];

        foreach ($data['hits'] as $hit) {
            $nome = $hit['title'] ?? null;
            $url  = $hit['url'] ?? null;

            if (empty($nome) || empty($url)) {
                continue;
            }

            // Preço: usa o valor à vista (cash), fallback para full
            $preco = null;
            if (!empty($hit['price']['cash'])) {
                $preco = (float) $hit['price']['cash'];
            } elseif (!empty($hit['price']['full'])) {
                $preco = (float) $hit['price']['full'];
            }

            // Imagem: primeira do array, monta URL completa
            $imagem = null;
            if (!empty($hit['images'][0])) {
                $imagem = self::IMAGE_CDN . '/' . ltrim($hit['images'][0], '/');
            }

            // Descrição: decodifica entidades HTML e limpa
            $descricao = null;
            if (!empty($hit['description'])) {
                $descricao = html_entity_decode(strip_tags($hit['description']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $descricao = mb_substr($descricao, 0, 300);
            }

            $resultados[] = [
                'nome'      => $nome,
                'descricao' => $descricao,
                'preco'     => $preco,
                'url'       => $url,
                'imagem'    => $imagem,
            ];
        }

        return $resultados;
    }
}
