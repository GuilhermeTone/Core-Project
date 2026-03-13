<?php

namespace App\Jobs;

use App\Models\FerramentaBusca;
use App\Models\ResultadoBusca;
use App\Services\CrawlerService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuscarNoSiteJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;
    public int $tries   = 2;

    public function __construct(
        public readonly int    $buscaId,
        public readonly string $scraperIdentificador,
    ) {}

    public function handle(CrawlerService $crawler): void
    {
        // Se o batch foi cancelado (ex: outro job falhou com allowFailures=false), aborta
        if ($this->batch()?->cancelled()) {
            return;
        }

        $busca = FerramentaBusca::findOrFail($this->buscaId);

        try {
            $scraper    = $crawler->getScraper($this->scraperIdentificador);
            $resultados = $scraper->buscar($busca->termo);

            foreach ($resultados as $item) {
                if (!$this->eRelevante($item['nome'] ?? '', $busca->termo)) {
                    Log::debug("BuscarNoSiteJob [{$this->scraperIdentificador}] descartado por irrelevância: \"{$item['nome']}\" para termo \"{$busca->termo}\"");
                    continue;
                }

                ResultadoBusca::create([
                    'ferramenta_busca_id' => $busca->id,
                    'site'                => $scraper->identificador(),
                    'nome'                => $item['nome'],
                    'descricao'           => $item['descricao'] ?? null,
                    'preco'               => $item['preco'],
                    'url'                 => $item['url'],
                    'imagem'              => $item['imagem'] ?? null,
                    'mais_barato'         => false,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("BuscarNoSiteJob [{$this->scraperIdentificador}] erro: " . $e->getMessage());
            // Não re-lança: permite que os outros sites do batch continuem
        }
    }

    /**
     * Verifica se o nome do produto tem ao menos uma palavra significativa do termo buscado.
     * "Significativa" = 3+ caracteres (ignora preposições como "de", "da", "em", "com").
     */
    private function eRelevante(string $nomeItem, string $termoBusca): bool
    {
        if (empty($nomeItem)) {
            return false;
        }

        $normItem  = $this->normalizar($nomeItem);
        $normTermo = $this->normalizar($termoBusca);

        $palavras = preg_split('/\s+/', $normTermo, -1, PREG_SPLIT_NO_EMPTY);
        $palavrasSignificativas = array_filter($palavras, fn (string $p) => mb_strlen($p) >= 3);

        if (empty($palavrasSignificativas)) {
            return true; // termo muito curto — aceita tudo
        }

        foreach ($palavrasSignificativas as $palavra) {
            if (mb_strpos($normItem, $palavra) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove acentos e coloca em minúsculas para comparação.
     */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $mapa  = [
            '/[àáâãä]/u' => 'a',
            '/[èéêë]/u'  => 'e',
            '/[ìíîï]/u'  => 'i',
            '/[òóôõö]/u' => 'o',
            '/[ùúûü]/u'  => 'u',
            '/[ç]/u'     => 'c',
            '/[ñ]/u'     => 'n',
        ];

        return preg_replace(array_keys($mapa), array_values($mapa), $texto);
    }
}
