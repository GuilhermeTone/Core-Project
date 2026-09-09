<?php

namespace App\Jobs;

use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Services\Planilhas\XlsxCotacaoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizarPlanilhaCotacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $planilhaId) {}

    public function handle(XlsxCotacaoService $xlsx): void
    {
        $planilha = PlanilhaCotacao::findOrFail($this->planilhaId);

        try {
            $this->normalizarItensPendentes($planilha);

            $planilha->refresh()->load('itens');
            $arquivoProcessado = $xlsx->gerarPlanilhaProcessada($planilha);
            $itensProcessados = $planilha->itens()
                ->whereIn('status', ['concluido', 'sem_resultado', 'erro'])
                ->count();

            $planilha->update([
                'status' => 'concluido',
                'arquivo_processado' => $arquivoProcessado,
                'itens_processados' => $itensProcessados,
                'erro_mensagem' => null,
            ]);
        } catch (\Throwable $e) {
            $planilha->update([
                'status' => 'erro',
                'erro_mensagem' => $e->getMessage(),
            ]);
        }
    }

    private function normalizarItensPendentes(PlanilhaCotacao $planilha): void
    {
        $planilha->itens()
            ->whereIn('status', ['pendente', 'processando'])
            ->get()
            ->each(function (PlanilhaCotacaoItem $item): void {
                $resultados = $item->resultados ?? [];
                $totalLojas = max((int) $item->lojas_total, 0);

                $item->update([
                    'status' => ! empty($resultados) ? 'concluido' : 'sem_resultado',
                    'lojas_processadas' => max((int) $item->lojas_processadas, $totalLojas),
                ]);
            });
    }
}
