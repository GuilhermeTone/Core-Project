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

    public int $tries = 2;

    public function __construct(
        public readonly int $buscaId,
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
            $resultados = $crawler->buscarEmLoja($busca->termo, $this->scraperIdentificador);

            foreach ($resultados as $item) {
                ResultadoBusca::create([
                    'ferramenta_busca_id' => $busca->id,
                    'site' => $item['site'] ?? $this->scraperIdentificador,
                    'nome' => $item['nome'],
                    'descricao' => $item['descricao'] ?? null,
                    'preco' => $item['preco'],
                    'url' => $item['url'],
                    'imagem' => $item['imagem'] ?? null,
                    'mais_barato' => false,
                    'marca_detectada' => $item['marca_detectada'] ?? null,
                    'score_confianca_marca' => $item['score_confianca_marca'] ?? null,
                    'atributos_extraidos' => $item['atributos_extraidos'] ?? null,
                    'score_produto' => $item['score_produto'] ?? null,
                    'correspondencia_fraca' => $item['correspondencia_fraca'] ?? false,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("BuscarNoSiteJob [{$this->scraperIdentificador}] erro: ".$e->getMessage());
            // Não re-lança: permite que os outros sites do batch continuem
        }
    }
}
