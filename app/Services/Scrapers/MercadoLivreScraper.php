<?php

namespace App\Services\Scrapers;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

    protected function executarBusca(string $termo): array
    {
        $token = $this->accessToken();
        if (empty($token)) {
            return [];
        }

        $url = 'https://api.mercadolibre.com/sites/MLB/search?'
            . http_build_query(['q' => $termo, 'limit' => 10]);

        try {
            $response = $this->client->get($url, [
                'headers' => ['Authorization' => "Bearer {$token}"],
            ]);

            $data = json_decode((string) $response->getBody(), true);
        } catch (\Exception $e) {
            Log::warning('[mercadolivre] Busca falhou: ' . $e->getMessage());
            return [];
        }

        if (empty($data['results'])) {
            return [];
        }

        $resultados = [];

        foreach ($data['results'] as $item) {
            $nome = $item['title'] ?? '';
            if (empty($nome)) {
                continue;
            }

            $resultados[] = [
                'nome'      => $nome,
                'descricao' => null,
                'preco'     => isset($item['price']) ? (float) $item['price'] : null,
                'url'       => $item['permalink'] ?? '',
                'imagem'    => isset($item['thumbnail'])
                    ? str_replace('I.jpg', 'O.jpg', $item['thumbnail'])
                    : null,
            ];
        }

        return $resultados;
    }

    // -------------------------------------------------------------------------

    private function accessToken(): string
    {
        $appId     = config('services.mercadolivre.app_id');
        $secret    = config('services.mercadolivre.secret');

        if (empty($appId) || empty($secret)) {
            Log::warning('[mercadolivre] ML_APP_ID ou ML_SECRET não configurados.');
            return '';
        }

        return Cache::remember('ml_access_token', 21_600, function () use ($appId, $secret) {
            try {
                $client = new Client(['timeout' => 10, 'verify' => false]);
                $response = $client->post('https://api.mercadolibre.com/oauth/token', [
                    'form_params' => [
                        'grant_type'    => 'client_credentials',
                        'client_id'     => $appId,
                        'client_secret' => $secret,
                    ],
                ]);

                $data = json_decode((string) $response->getBody(), true);
                return $data['access_token'] ?? '';
            } catch (\Exception $e) {
                Log::warning('[mercadolivre] Falha ao obter access_token: ' . $e->getMessage());
                return '';
            }
        });
    }
}
