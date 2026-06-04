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
use Illuminate\Support\Facades\DB;

class ProcessarPlanilhaCotacaoItemLojaJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;
    public int $tries = 1;

    public function __construct(
        public readonly int $itemId,
        public readonly string $scraperIdentificador,
    ) {}

    public function handle(CrawlerService $crawler): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $item = PlanilhaCotacaoItem::findOrFail($this->itemId);
        $resultadosResumo = [];
        $erro = null;

        try {
            $resultados = $crawler->buscarEmLoja($item->descricao, $this->scraperIdentificador);
            $resultados = array_values(array_filter(
                $resultados,
                fn (array $resultado): bool => (float) ($resultado['preco'] ?? 0) > 0,
            ));

            $resultadosResumo = array_map(
                fn (array $resultado): array => [
                    'site' => $resultado['site'] ?? $this->scraperIdentificador,
                    'nome_site' => $resultado['nome_site'] ?? null,
                    'nome' => $resultado['nome'] ?? null,
                    'preco' => isset($resultado['preco']) ? (float) $resultado['preco'] : null,
                    'url' => $resultado['url'] ?? null,
                    'imagem' => $resultado['imagem'] ?? null,
                    'marca_detectada' => $resultado['marca_detectada'] ?? null,
                    'score_produto' => $resultado['score_produto'] ?? null,
                ],
                $resultados,
            );
        } catch (\Throwable $e) {
            $erro = "{$this->scraperIdentificador}: {$e->getMessage()}";
        }

        DB::transaction(function () use ($resultadosResumo, $erro): void {
            $item = PlanilhaCotacaoItem::whereKey($this->itemId)->lockForUpdate()->firstOrFail();

            $resultados = array_merge($item->resultados ?? [], $resultadosResumo);
            $resultados = $this->resultadosUnicosOrdenados($resultados);
            $lojasProcessadas = min(($item->lojas_processadas ?? 0) + 1, max((int) $item->lojas_total, 1));
            $erros = trim(implode("\n", array_filter([$item->erro_mensagem, $erro])));
            $finalizouItem = $lojasProcessadas >= (int) $item->lojas_total;
            $statusAnterior = $item->status;

            $updates = [
                'lojas_processadas' => $lojasProcessadas,
                'resultados' => array_slice($resultados, 0, 20),
                'erro_mensagem' => $erros !== '' ? $erros : null,
            ];

            if ($finalizouItem && ! in_array($statusAnterior, ['concluido', 'sem_resultado', 'erro'], true)) {
                $updates['status'] = ! empty($resultados) ? 'concluido' : 'sem_resultado';
            }

            $item->update($updates);

            if ($finalizouItem && ! in_array($statusAnterior, ['concluido', 'sem_resultado', 'erro'], true)) {
                $item->planilha()->increment('itens_processados');
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $resultados
     * @return array<int, array<string, mixed>>
     */
    private function resultadosUnicosOrdenados(array $resultados): array
    {
        $vistos = [];
        $unicos = [];

        foreach ($resultados as $resultado) {
            $chave = md5(strtolower(trim(($resultado['site'] ?? '').'|'.($resultado['url'] ?? '').'|'.($resultado['nome'] ?? ''))));

            if (isset($vistos[$chave])) {
                continue;
            }

            $vistos[$chave] = true;
            $unicos[] = $resultado;
        }

        usort($unicos, fn (array $a, array $b): int => ((float) ($a['preco'] ?? PHP_FLOAT_MAX)) <=> ((float) ($b['preco'] ?? PHP_FLOAT_MAX)));

        return $unicos;
    }
}
