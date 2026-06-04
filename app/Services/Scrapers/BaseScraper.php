<?php

namespace App\Services\Scrapers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

abstract class BaseScraper implements ScraperInterface
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'headers' => [
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection'      => 'keep-alive',
            ],
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => false,
            'verify'          => false,
        ]);
    }

    /**
     * Template method: cleans the term, delegates to executarBusca(), then
     * filters and ranks the raw results by relevance before returning.
     *
     * {@inheritdoc}
     */
    final public function buscar(string $termo): array
    {
        $termoLimpo = QueryNormalizer::limpar($termo);
        $resultados = $this->executarBusca($termoLimpo);

        return RelevanceFilter::filtrar($resultados, $termoLimpo);
    }

    /**
     * Performs the actual site-specific search and returns raw results.
     * Subclasses implement this instead of buscar().
     *
     * @return array<array{nome: string, descricao: string|null, preco: float|null, url: string, imagem: string|null}>
     */
    abstract protected function executarBusca(string $termo): array;

    /**
     * Searches via VTEX Intelligent Search API, which matches the results shown
     * on the site's frontend (unlike the legacy Catalog API which uses a different ranking).
     *
     * Endpoint: /api/io/_v/api/intelligent-search/product_search/trade-policy/1
     *
     * @return array<array{nome: string, descricao: string|null, preco: float|null, url: string, imagem: string|null, codigo: string|null}>
     */
    protected function buscarVtexIS(string $dominio, string $termo): array
    {
        $query = rawurlencode($termo);
        $base  = "https://{$dominio}";
        $url   = "{$base}/api/io/_v/api/intelligent-search/product_search/trade-policy/1"
               . "?query={$query}&operator=and&fuzzy=auto&from=0&to=9";

        $data = json_decode($this->get($url), true);

        $products = $data['products'] ?? [];

        if (empty($products)) {
            return [];
        }

        $resultados = [];

        foreach ($products as $product) {
            $nome = $product['productName'] ?? null;
            $link = $product['link']        ?? null;

            if (empty($nome) || empty($link)) {
                continue;
            }

            // IS returns relative links — prepend the domain
            if (!str_starts_with($link, 'http')) {
                $link = $base . $link;
            }

            $preco  = null;
            $imagem = null;
            $item   = $product['items'][0] ?? null;

            if ($item) {
                $preco  = $this->precoPrincipalVtex($item['sellers'][0]['commertialOffer'] ?? []);
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
                'codigo'    => $product['productReference'] ?? null,
            ];
        }

        return $resultados;
    }

    /**
     * VTEX exposes spotPrice for Pix/boleto discounts. For quotation we use the
     * regular item price, falling back only to comparable non-spot fields.
     *
     * @param  array<string, mixed>  $offer
     */
    protected function precoPrincipalVtex(array $offer): ?float
    {
        foreach (['PriceWithoutDiscount', 'Price', 'ListPrice'] as $field) {
            if (isset($offer[$field]) && (float) $offer[$field] > 0) {
                return (float) $offer[$field];
            }
        }

        return null;
    }

    protected function precoPorTexto(?string $texto): ?float
    {
        if (empty($texto)) {
            return null;
        }

        if (preg_match('/R\$\s*((?:\d{1,3}(?:\.\d{3})*|\d+)(?:,\d{2})?)/u', $texto, $m)) {
            $valorTexto = $m[1];
        } elseif (preg_match('/(\d{1,3}(?:\.\d{3})*|\d+)(?:,\d{2})?/', $texto, $m)) {
            $valorTexto = $m[0];
        } else {
            return null;
        }

        $valor = str_replace('.', '', $valorTexto);
        $valor = str_replace(',', '.', $valor);

        return is_numeric($valor) && (float) $valor > 0 ? (float) $valor : null;
    }

    protected function urlAbsoluta(string $base, ?string $href): string
    {
        $href = trim((string) $href);

        if ($href === '') {
            return $base;
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        if (str_starts_with($href, 'http')) {
            return $href;
        }

        return rtrim($base, '/') . '/' . ltrim($href, '/');
    }

    protected function textoPrimeiro(Crawler $node, array $seletores): ?string
    {
        foreach ($seletores as $seletor) {
            try {
                $texto = trim($node->filter($seletor)->first()->text());

                if ($texto !== '') {
                    return html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            } catch (\Exception $e) {
            }
        }

        return null;
    }

    protected function atributoPrimeiro(Crawler $node, array $seletores, string $atributo): ?string
    {
        foreach ($seletores as $seletor) {
            try {
                $valor = $node->filter($seletor)->first()->attr($atributo);

                if (!empty($valor)) {
                    return html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            } catch (\Exception $e) {
            }
        }

        return null;
    }

    /**
     * Loja Integrada cards expose the regular card price in data-sell-price and
     * the Pix discount in a sibling text. Use data-sell-price for quotations.
     *
     * @return array<array{nome: string, descricao: string|null, preco: float|null, url: string, imagem: string|null, codigo: string|null}>
     */
    protected function buscarLojaIntegrada(string $base, string $url): array
    {
        $html = $this->get($url);

        if (empty($html)) {
            return [];
        }

        $crawler = new Crawler($html);
        $resultados = [];

        try {
            $crawler->filter('.listagem-item')->each(function (Crawler $node) use (&$resultados, $base) {
                $nome = $this->textoPrimeiro($node, ['.nome-produto', 'a[title]']);
                $href = $this->atributoPrimeiro($node, ['.nome-produto', '.produto-sobrepor', 'a[href]'], 'href');

                if (empty($nome) || empty($href)) {
                    return;
                }

                $preco = null;
                $dataSellPrice = $this->atributoPrimeiro($node, ['.preco-promocional[data-sell-price]'], 'data-sell-price');
                if (is_numeric($dataSellPrice) && (float) $dataSellPrice > 0) {
                    $preco = (float) $dataSellPrice;
                }

                if ($preco === null) {
                    $preco = $this->precoPorTexto($this->textoPrimeiro($node, ['.preco-promocional']));
                }

                $codigo = $this->textoPrimeiro($node, ['.produto-sku']);

                $resultados[] = [
                    'nome'      => $nome,
                    'descricao' => null,
                    'preco'     => $preco,
                    'url'       => $this->urlAbsoluta($base, $href),
                    'imagem'    => $this->atributoPrimeiro($node, ['.imagem-produto img', 'img'], 'src')
                        ?? $this->atributoPrimeiro($node, ['.imagem-produto img', 'img'], 'data-src'),
                    'codigo'    => $codigo,
                ];
            });
        } catch (\Exception $e) {
        }

        return array_slice($resultados, 0, 10);
    }

    /**
     * Extracts a balanced JS/JSON array after a marker, respecting quoted strings.
     */
    protected function extrairArrayJsApos(string $html, string $marcador): ?string
    {
        $pos = strpos($html, $marcador);

        if ($pos === false) {
            return null;
        }

        $inicio = strpos($html, '[', $pos);

        if ($inicio === false) {
            return null;
        }

        $nivel = 0;
        $emString = false;
        $aspas = '';
        $escape = false;
        $len = strlen($html);

        for ($i = $inicio; $i < $len; $i++) {
            $char = $html[$i];

            if ($emString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }

                if ($char === '\\') {
                    $escape = true;
                    continue;
                }

                if ($char === $aspas) {
                    $emString = false;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $emString = true;
                $aspas = $char;
                continue;
            }

            if ($char === '[') {
                $nivel++;
            }

            if ($char === ']') {
                $nivel--;

                if ($nivel === 0) {
                    return substr($html, $inicio, $i - $inicio + 1);
                }
            }
        }

        return null;
    }

    /**
     * Performs a GET request and returns the response body.
     * Returns an empty string on failure.
     */
    protected function get(string $url): string
    {
        try {
            $response = $this->client->get($url);
            return (string) $response->getBody();
        } catch (GuzzleException $e) {
            Log::warning("[{$this->identificador()}] GET falhou para {$url}: " . $e->getMessage());
            return '';
        } catch (\Exception $e) {
            Log::warning("[{$this->identificador()}] Erro inesperado para {$url}: " . $e->getMessage());
            return '';
        }
    }
}
