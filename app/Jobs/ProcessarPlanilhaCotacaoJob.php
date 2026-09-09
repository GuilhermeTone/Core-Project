<?php

namespace App\Jobs;

use App\Models\PlanilhaCotacao;
use App\Services\CrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ProcessarPlanilhaCotacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public function __construct(public readonly int $planilhaId) {}

    public function handle(CrawlerService $crawler): void
    {
        $planilha = PlanilhaCotacao::with('itens')->findOrFail($this->planilhaId);

        $planilha->update([
            'status' => 'processando',
            'total_itens' => $planilha->itens->count(),
            'itens_processados' => 0,
        ]);

        $identificadores = $crawler->getIdentificadores();
        $totalLojas = count($identificadores);

        $planilha->itens->each(function ($item) use ($totalLojas): void {
            $item->update([
                'status' => 'processando',
                'lojas_total' => $totalLojas,
                'lojas_processadas' => 0,
                'marca_cotada' => null,
                'preco_loja' => null,
                'preco_revalidado' => null,
                'valor_unitario' => null,
                'resultado_escolhido' => null,
                'revalidacao_status' => null,
                'revalidado_em' => null,
                'revalidacao_mensagem' => null,
                'resultados' => [],
                'erro_mensagem' => null,
            ]);
        });

        $jobs = $planilha->itens
            ->flatMap(fn ($item) => array_map(
                fn (string $identificador) => new ProcessarPlanilhaCotacaoItemLojaJob($item->id, $identificador),
                $identificadores,
            ))
            ->all();

        $planilhaId = $this->planilhaId;

        if (empty($jobs)) {
            FinalizarPlanilhaCotacaoJob::dispatch($planilhaId);

            return;
        }

        Bus::batch($jobs)
            ->name("Planilha #{$planilha->id}")
            ->allowFailures()
            ->then(fn () => FinalizarPlanilhaCotacaoJob::dispatch($planilhaId))
            ->catch(function ($batch, \Throwable $e) use ($planilhaId): void {
                Log::warning("Planilha #{$planilhaId} teve falha parcial: ".$e->getMessage());
                FinalizarPlanilhaCotacaoJob::dispatch($planilhaId);
            })
            ->dispatch();
    }
}
