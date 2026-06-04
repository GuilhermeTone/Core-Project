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

    protected function executarBusca(string $termo): array
    {
        return $this->buscarVtexIS('www.fermaquinas.com.br', $termo);
    }
}
