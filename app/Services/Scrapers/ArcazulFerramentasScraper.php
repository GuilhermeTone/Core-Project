<?php

namespace App\Services\Scrapers;

class ArcazulFerramentasScraper extends BaseScraper
{
    private const BASE_URL = 'https://www.arcazulferramentas.com.br';

    public function identificador(): string
    {
        return 'arcazul';
    }

    public function nomeSite(): string
    {
        return 'Arcazul Ferramentas';
    }

    protected function executarBusca(string $termo): array
    {
        return $this->buscarLojaIntegrada(self::BASE_URL, self::BASE_URL . '/buscar?q=' . urlencode($termo));
    }
}
