<?php

namespace App\Jobs;

use App\Models\PlanilhaCotacaoItem;
use App\Services\CrawlerService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessarPlanilhaCotacaoItemJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(public readonly int $itemId) {}

    public function handle(CrawlerService $crawler): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $item = PlanilhaCotacaoItem::findOrFail($this->itemId);
        $item->update(['status' => 'processando']);

        try {
            $resultados = $crawler->buscar(trim((string) ($item->termo_busca ?: $item->descricao)));
            $resultados = array_values(array_filter(
                $resultados,
                fn (array $resultado): bool => (float) ($resultado['preco'] ?? 0) > 0,
            ));

            usort($resultados, fn (array $a, array $b): int => ((float) $a['preco']) <=> ((float) $b['preco']));

            $resultadosResumo = array_map(
                fn (array $resultado): array => [
                    'site' => $resultado['site'] ?? null,
                    'nome_site' => $resultado['nome_site'] ?? null,
                    'nome' => $resultado['nome'] ?? null,
                    'preco' => isset($resultado['preco']) ? (float) $resultado['preco'] : null,
                    'url' => $resultado['url'] ?? null,
                    'imagem' => $resultado['imagem'] ?? null,
                    'marca_detectada' => $resultado['marca_detectada'] ?? null,
                    'score_produto' => $resultado['score_produto'] ?? null,
                    'atributos_extraidos' => $resultado['atributos_extraidos'] ?? null,
                    'codigo' => $resultado['codigo'] ?? null,
                    'disponivel' => $resultado['disponivel'] ?? true,
                    'capturado_em' => now()->toIso8601String(),
                ],
                array_slice($resultados, 0, 20),
            );

            $item->update([
                'status' => ! empty($resultadosResumo) ? 'concluido' : 'sem_resultado',
                'marca_cotada' => null,
                'preco_loja' => null,
                'preco_revalidado' => null,
                'valor_unitario' => null,
                'resultado_escolhido' => null,
                'revalidacao_status' => null,
                'revalidado_em' => null,
                'revalidacao_mensagem' => null,
                'resultados' => $resultadosResumo,
                'erro_mensagem' => null,
            ]);
        } catch (\Throwable $e) {
            $item->update([
                'status' => 'erro',
                'erro_mensagem' => $e->getMessage(),
            ]);
        } finally {
            $item->planilha()->increment('itens_processados');
        }
    }
}
