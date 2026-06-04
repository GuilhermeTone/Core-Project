<?php

namespace App\Services\Scrapers;

class DimensionalScraper extends BaseScraper
{
    public function identificador(): string
    {
        return 'dimensional';
    }

    public function nomeSite(): string
    {
        return 'Dimensional';
    }

    protected function executarBusca(string $termo): array
    {
        return $this->buscarVtexIS('www.dimensional.com.br', $termo);
    }
}
