<?php

namespace App\Services\Scrapers;

class CasaFrentistaScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.casadofrentista.com.br';

    public function identificador(): string
    {
        return 'casadofrentista';
    }

    public function nomeSite(): string
    {
        return 'Casa do Frentista';
    }

    protected function executarBusca(string $termo): array
    {
        $html = $this->get(self::BASE_URL . '/busca?q=' . urlencode($termo));

        if (empty($html)) {
            return [];
        }

        $array = $this->extrairArrayJsApos($html, 'itens:');
        $itens = $array ? json_decode($array, true) : null;

        if (!is_array($itens)) {
            return [];
        }

        $resultados = [];

        foreach ($itens as $item) {
            if (empty($item['nome']) || empty($item['link'])) {
                continue;
            }

            $resultados[] = [
                'nome'      => html_entity_decode((string) $item['nome'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'descricao' => $item['marca'] ?? null,
                'preco'     => isset($item['valor']) && is_numeric($item['valor']) ? (float) $item['valor'] : null,
                'url'       => $this->urlAbsoluta(self::BASE_URL, $item['link']),
                'imagem'    => $item['midia_url'] ?? null,
                'codigo'    => $item['codigo'] ?? null,
                'disponivel' => $this->disponibilidadePorCampos($item) ?? $this->disponibilidadePorTexto(json_encode($item, JSON_UNESCAPED_UNICODE)),
            ];
        }

        return array_slice($resultados, 0, 10);
    }
}
