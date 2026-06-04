<?php

namespace App\Services\Scrapers;

class LFMaquinasScraper extends BaseScraper
{
    public function identificador(): string
    {
        return 'lfmaquinas';
    }

    public function nomeSite(): string
    {
        return 'LF Máquinas e Ferramentas';
    }

    protected function executarBusca(string $termo): array
    {
        return $this->buscarVtexIS('www.lfmaquinaseferramentas.com.br', $termo);
    }
}
