<?php

namespace App\Jobs;

use App\Models\FerramentaBusca;
use App\Services\CrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Job coordenador: cria um batch paralelo com um job por scraper.
 * Cada BuscarNoSiteJob salva seus resultados assim que termina.
 * Ao final do batch, FinalizarBuscaJob calcula o mais barato global.
 */
class BuscarFerramentaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries   = 1;

    public function __construct(public readonly int $buscaId) {}

    public function handle(CrawlerService $crawler): void
    {
        $busca = FerramentaBusca::findOrFail($this->buscaId);

        $identificadores = $crawler->getIdentificadoresFiltrados($busca->lojas);

        $busca->update([
            'status'      => 'processando',
            'total_sites' => count($identificadores),
        ]);

        $jobs = array_map(
            fn (string $id) => new BuscarNoSiteJob($this->buscaId, $id),
            $identificadores,
        );

        $buscaId = $this->buscaId;

        Bus::batch($jobs)
            ->name("Busca: {$busca->termo}")
            ->allowFailures()   // um site falhando não cancela os outros
            ->then(function () use ($buscaId) {
                FinalizarBuscaJob::dispatch($buscaId);
            })
            ->catch(function (\Illuminate\Bus\Batch $batch, \Throwable $e) use ($buscaId) {
                Log::error("Batch busca #{$buscaId} com falha: " . $e->getMessage());
                // Mesmo com falha parcial, tenta finalizar com o que tiver
                FinalizarBuscaJob::dispatch($buscaId);
            })
            ->dispatch();
    }
}
