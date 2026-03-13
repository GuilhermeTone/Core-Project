<?php

namespace App\Jobs;

use App\Models\FerramentaBusca;
use App\Models\ResultadoBusca;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizarBuscaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $buscaId) {}

    public function handle(): void
    {
        $busca = FerramentaBusca::findOrFail($this->buscaId);

        // Limpa qualquer flag antiga
        ResultadoBusca::where('ferramenta_busca_id', $this->buscaId)
            ->update(['mais_barato' => false]);

        // Marca o item mais barato globalmente (entre todos os sites)
        $maisBarato = ResultadoBusca::where('ferramenta_busca_id', $this->buscaId)
            ->whereNotNull('preco')
            ->where('preco', '>', 0)
            ->orderBy('preco')
            ->first();

        if ($maisBarato) {
            $maisBarato->update(['mais_barato' => true]);
        }

        $busca->update(['status' => 'concluido']);
    }
}
