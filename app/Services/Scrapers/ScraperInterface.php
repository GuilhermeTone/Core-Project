<?php

namespace App\Services\Scrapers;

interface ScraperInterface
{
    /**
     * Returns the unique identifier for this scraper (e.g. 'mercadolivre').
     */
    public function identificador(): string;

    /**
     * Returns the human-readable site name (e.g. 'Mercado Livre').
     */
    public function nomeSite(): string;

    /**
     * Searches for the given term and returns an array of product arrays.
     * Each product array should contain: nome, descricao, preco, url, imagem.
     */
    public function buscar(string $termo): array;
}
