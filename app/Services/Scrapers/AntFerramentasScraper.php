<?php

namespace App\Services\Scrapers;

class AntFerramentasScraper extends BaseScraper
{
    public function identificador(): string
    {
        return 'antferramentas';
    }

    public function nomeSite(): string
    {
        return 'ANT Ferramentas';
    }

    protected function executarBusca(string $termo): array
    {
        return $this->buscarVtexIS('www.antferramentas.com.br', $termo);
    }
}
