<?php

namespace App\Services\Scrapers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

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
