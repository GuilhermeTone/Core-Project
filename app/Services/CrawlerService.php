<?php

namespace App\Services;

use App\Services\Scrapers\AnhangueraScraper;
use App\Services\Scrapers\AntFerramentasScraper;
use App\Services\Scrapers\FerMaquinasScraper;
use App\Services\Scrapers\KennedyScraper;
use App\Services\Scrapers\LFMaquinasScraper;
use App\Services\Scrapers\LojaMecanicoScraper;
use App\Services\Scrapers\MaboreScraper;
use App\Services\Scrapers\MartineliScraper;
use App\Services\Scrapers\MercadoLivreScraper;
use App\Services\Scrapers\ScraperInterface;
use Illuminate\Support\Facades\Log;

class CrawlerService
{
    /** @var ScraperInterface[] */
    private array $scrapers;

    public function __construct()
    {
        $this->scrapers = [
            'mercadolivre'   => new MercadoLivreScraper(),
            'lojadomecanico' => new LojaMecanicoScraper(),
            'anhanguera'     => new AnhangueraScraper(),
            'antferramentas' => new AntFerramentasScraper(),
            'kennedy'        => new KennedyScraper(),
            'lfmaquinas'     => new LFMaquinasScraper(),
            'martineli'      => new MartineliScraper(),
            'mabore'         => new MaboreScraper(),
            'fermaquinas'    => new FerMaquinasScraper(),
        ];
    }

    /** Retorna os identificadores de todos os scrapers registrados. */
    public function getIdentificadores(): array
    {
        return array_keys($this->scrapers);
    }

    /**
     * Retorna lista de todos os scrapers com id e nome para exibição na UI.
     * @return array<array{id: string, nome: string}>
     */
    public function getListaLojas(): array
    {
        return array_values(array_map(
            fn (ScraperInterface $s) => ['id' => $s->identificador(), 'nome' => $s->nomeSite()],
            $this->scrapers,
        ));
    }

    /**
     * Retorna identificadores filtrando pelos fornecidos.
     * Se $lojas estiver vazio ou null, retorna todos.
     *
     * @param  array<string>|null $lojas
     * @return array<string>
     */
    public function getIdentificadoresFiltrados(?array $lojas): array
    {
        $todos = $this->getIdentificadores();

        if (empty($lojas)) {
            return $todos;
        }

        return array_values(array_intersect($todos, $lojas));
    }

    /** Retorna um scraper específico pelo identificador. */
    public function getScraper(string $identificador): ScraperInterface
    {
        if (!isset($this->scrapers[$identificador])) {
            throw new \InvalidArgumentException("Scraper '{$identificador}' não encontrado.");
        }

        return $this->scrapers[$identificador];
    }

    /** Executa todos os scrapers sequencialmente (usado em testes/tinker). */
    public function buscar(string $termo): array
    {
        $todos = [];

        foreach ($this->scrapers as $scraper) {
            try {
                $resultados = $scraper->buscar($termo);
                foreach ($resultados as $item) {
                    $todos[] = array_merge($item, ['site' => $scraper->identificador()]);
                }
            } catch (\Exception $e) {
                Log::warning("Scraper {$scraper->identificador()} falhou: " . $e->getMessage());
            }
        }

        return $todos;
    }

    /** Registra um novo scraper em tempo de execução. */
    public function addScraper(ScraperInterface $scraper): void
    {
        $this->scrapers[$scraper->identificador()] = $scraper;
    }
}
