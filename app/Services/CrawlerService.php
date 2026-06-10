<?php

namespace App\Services;

use App\Services\Scrapers\AgreliMaquinasScraper;
use App\Services\Scrapers\AnhangueraScraper;
use App\Services\Scrapers\AntFerramentasScraper;
use App\Services\Scrapers\ArcazulFerramentasScraper;
use App\Services\Scrapers\BrenfeerScraper;
use App\Services\Scrapers\CasaFrentistaScraper;
use App\Services\Scrapers\DimensionalScraper;
use App\Services\Scrapers\FerMaquinasScraper;
use App\Services\Scrapers\GraviaScraper;
use App\Services\Scrapers\KennedyScraper;
use App\Services\Scrapers\LFMaquinasScraper;
use App\Services\Scrapers\LojaMecanicoScraper;
use App\Services\Scrapers\MaboreScraper;
use App\Services\Scrapers\MartineliScraper;
use App\Services\Scrapers\MinasFerramentasScraper;
use App\Services\Scrapers\PalacioFerramentasScraper;
use App\Services\Scrapers\ScraperInterface;
use App\Services\Scrapers\TramontinaScraper;
use Illuminate\Support\Facades\Log;

class CrawlerService
{
    /** @var ScraperInterface[] */
    private array $scrapers;

    public function __construct()
    {
        $this->scrapers = [
            'lojadomecanico' => new LojaMecanicoScraper,
            'anhanguera' => new AnhangueraScraper,
            'antferramentas' => new AntFerramentasScraper,
            'kennedy' => new KennedyScraper,
            'lfmaquinas' => new LFMaquinasScraper,
            'martineli' => new MartineliScraper,
            'mabore' => new MaboreScraper,
            'fermaquinas' => new FerMaquinasScraper,
            'casadofrentista' => new CasaFrentistaScraper,
            'agrelimaquinas' => new AgreliMaquinasScraper,
            'palaciodasferramentas' => new PalacioFerramentasScraper,
            'brenfeer' => new BrenfeerScraper,
            'tramontinaoficial' => new TramontinaScraper,
            'dimensional' => new DimensionalScraper,
            'gravia' => new GraviaScraper,
            'arcazul' => new ArcazulFerramentasScraper,
            'minasferramentas' => new MinasFerramentasScraper
        ];
    }

    /** Retorna os identificadores de todos os scrapers registrados. */
    public function getIdentificadores(): array
    {
        return array_keys($this->scrapers);
    }

    /**
     * Retorna lista de todos os scrapers com id e nome para exibição na UI.
     *
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
     * @param  array<string>|null  $lojas
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
        if (! isset($this->scrapers[$identificador])) {
            throw new \InvalidArgumentException("Scraper '{$identificador}' não encontrado.");
        }

        return $this->scrapers[$identificador];
    }

    /** Executa todos os scrapers sequencialmente (usado em testes/tinker). */
    public function buscar(string $termo): array
    {
        $todos = [];

        foreach (array_keys($this->scrapers) as $identificador) {
            try {
                $todos = array_merge($todos, $this->buscarEmLoja($termo, $identificador));
            } catch (\Exception $e) {
                Log::warning("Scraper {$identificador} falhou: ".$e->getMessage());
            }
        }

        return $todos;
    }

    /**
     * Executa uma loja usando o mesmo motor da busca geral e da planilha.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buscarEmLoja(string $termo, string $identificador): array
    {
        $scraper = $this->getScraper($identificador);
        $resultadosBrutos = [];

        foreach (ProductEnrichmentService::termosBuscaPorMarca($termo) as $termoBusca) {
            $resultadosBrutos = array_merge($resultadosBrutos, $scraper->buscar($termoBusca));
        }

        $resultados = ProductEnrichmentService::enriquecerProdutos($this->removerDuplicados($resultadosBrutos), $termo);
        $resultados = array_values(array_filter(
            $resultados,
            fn (array $item): bool => ($item['disponivel'] ?? true) !== false,
        ));
        $resultados = ProductEnrichmentService::filtrarProdutosConfiaveis($resultados);

        return array_map(
            fn (array $item): array => array_merge($item, ['site' => $scraper->identificador()]),
            $resultados,
        );
    }

    /** Registra um novo scraper em tempo de execução. */
    public function addScraper(ScraperInterface $scraper): void
    {
        $this->scrapers[$scraper->identificador()] = $scraper;
    }

    /**
     * @param  array<int, array<string, mixed>>  $resultados
     * @return array<int, array<string, mixed>>
     */
    private function removerDuplicados(array $resultados): array
    {
        $vistos = [];
        $unicos = [];

        foreach ($resultados as $resultado) {
            $chave = md5(strtolower(trim(($resultado['url'] ?? '').'|'.($resultado['nome'] ?? ''))));

            if (isset($vistos[$chave])) {
                continue;
            }

            $vistos[$chave] = true;
            $unicos[] = $resultado;
        }

        return $unicos;
    }
}
