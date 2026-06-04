<?php

namespace App\Jobs;

use App\Models\FerramentaBusca;
use App\Models\ResultadoBusca;
use App\Services\SerperShoppingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuscarSerperShoppingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;
    public int $tries = 1;

    public function __construct(public readonly int $buscaId) {}

    public function handle(SerperShoppingService $serper): void
    {
        $busca = FerramentaBusca::findOrFail($this->buscaId);

        $busca->update([
            'status' => 'processando',
            'total_sites' => 1,
            'erro_mensagem' => null,
        ]);

        try {
            $resultados = $serper->buscar($busca->termo);

            foreach ($resultados as $indice => $item) {
                ResultadoBusca::create([
                    'ferramenta_busca_id' => $busca->id,
                    'site' => $item['site'] ?? 'serper',
                    'nome' => $item['nome'],
                    'descricao' => $item['descricao'] ?? null,
                    'preco' => $item['preco'],
                    'url' => $item['url'],
                    'imagem' => $item['imagem'] ?? null,
                    'mais_barato' => $indice === 0,
                    'marca_detectada' => $item['marca_detectada'] ?? null,
                    'score_confianca_marca' => $item['score_confianca_marca'] ?? null,
                    'atributos_extraidos' => array_filter(array_merge(
                        $item['atributos_extraidos'] ?? [],
                        ['loja_origem' => $item['loja_origem'] ?? null],
                    )),
                    'score_produto' => $item['score_produto'] ?? null,
                    'correspondencia_fraca' => false,
                ]);
            }

            $busca->update(['status' => 'concluido']);
        } catch (\Throwable $e) {
            $busca->update([
                'status' => 'erro',
                'erro_mensagem' => $e->getMessage(),
            ]);
        }
    }
}
