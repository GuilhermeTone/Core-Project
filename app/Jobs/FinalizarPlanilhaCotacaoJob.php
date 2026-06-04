<?php

namespace App\Jobs;

use App\Models\PlanilhaCotacao;
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
            $arquivoProcessado = $xlsx->gerarPlanilhaProcessada($planilha);

            $planilha->update([
                'status' => 'concluido',
                'arquivo_processado' => $arquivoProcessado,
                'erro_mensagem' => null,
            ]);
        } catch (\Throwable $e) {
            $planilha->update([
                'status' => 'erro',
                'erro_mensagem' => $e->getMessage(),
            ]);
        }
    }
}
