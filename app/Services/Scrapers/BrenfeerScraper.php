<?php

namespace App\Services\Scrapers;

class BrenfeerScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.brenfeer.com.br';

    public function identificador(): string
    {
        return 'brenfeer';
    }

    public function nomeSite(): string
    {
        return 'Brenfeer';
    }

    protected function executarBusca(string $termo): array
    {
        return $this->buscarLojaIntegrada(self::BASE_URL, self::BASE_URL . '/buscar?q=' . urlencode($termo));
    }
}
