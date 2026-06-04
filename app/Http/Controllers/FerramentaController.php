<?php

namespace App\Http\Controllers;

use App\Jobs\BuscarSerperShoppingJob;
use App\Models\FerramentaBusca;
use App\Models\ResultadoBusca;
use App\Services\ProductEnrichmentService;
use Illuminate\Http\Request;

class FerramentaController extends Controller
{
    public function index()
    {
        $marcasTrabalhadas = ProductEnrichmentService::marcasConhecidas();

        $buscasRecentes = FerramentaBusca::with(['resultados' => fn ($q) => $q->orderByRaw('mais_barato DESC')->orderBy('preco')])
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(20)
            ->get();

        $buscasJson = $buscasRecentes->map(fn (FerramentaBusca $b) => $this->mapearBusca($b))->values()->all();

        return view('ferramentas.index', compact('buscasRecentes', 'buscasJson', 'marcasTrabalhadas'));
    }

    public function buscar(Request $request)
    {
        $request->validate([
            'termo' => 'required|string|min:2|max:100',
        ]);

        $busca = FerramentaBusca::create([
            'user_id' => auth()->id(),
            'termo' => $request->termo,
            'lojas' => ['serper'],
            'total_sites' => 1,
        ]);

        BuscarSerperShoppingJob::dispatch($busca->id);

        return response()->json([
            'busca_id' => $busca->id,
            'status' => $busca->status,
            'message' => 'Busca iniciada',
        ]);
    }

    public function status(int $id)
    {
        $busca = FerramentaBusca::with(['resultados' => function ($q) {
            $q->orderByRaw('mais_barato DESC')->orderBy('preco');
        }])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $buscaMapeada = $this->mapearBusca($busca);

        return response()->json([
            'status' => $busca->status,
            'erro_mensagem' => $busca->erro_mensagem,
            'termo' => $busca->termo,
            'total_sites' => $busca->total_sites,
            'sites_concluidos' => $busca->status === 'concluido' ? 1 : 0,
            'total' => count($buscaMapeada['resultados']),
            'resultados' => $buscaMapeada['resultados'],
        ]);
    }

    public function destroy(int $id)
    {
        FerramentaBusca::where('user_id', auth()->id())->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearBusca(FerramentaBusca $busca): array
    {
        $resultados = $busca->resultados
            ->map(fn (ResultadoBusca $resultado) => $this->mapearResultado($resultado, $busca->termo))
            ->filter()
            ->values()
            ->all();

        $resultados = $this->recalcularMaisBarato($resultados);

        return [
            'id' => $busca->id,
            'termo' => $busca->termo,
            'status' => $busca->status,
            'erro_mensagem' => $busca->erro_mensagem,
            'total_sites' => $busca->total_sites,
            'sites_concluidos' => $busca->status === 'concluido' ? 1 : 0,
            'criado_em' => $busca->created_at->diffForHumans(),
            'resultados' => $resultados,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapearResultado(ResultadoBusca $resultado, string $termo): ?array
    {
        if ($resultado->correspondencia_fraca) {
            return null;
        }

        $nomeSite = $resultado->nome_site;
        if ($resultado->site === 'serper') {
            $nomeSite = $resultado->atributos_extraidos['loja_origem'] ?? $nomeSite;
        }

        return [
            'id' => $resultado->id,
            'site' => $resultado->site,
            'nome_site' => $nomeSite,
            'nome' => $resultado->nome,
            'descricao' => $resultado->descricao,
            'preco' => $resultado->preco,
            'preco_formatado' => $resultado->preco ? 'R$ '.number_format((float) $resultado->preco, 2, ',', '.') : 'Sem preço',
            'url' => $resultado->url,
            'imagem' => $resultado->imagem,
            'mais_barato' => false,
            'marca_detectada' => $resultado->marca_detectada,
            'score_confianca_marca' => $resultado->score_confianca_marca,
            'atributos_extraidos' => $resultado->atributos_extraidos,
            'score_produto' => $resultado->score_produto,
            'correspondencia_fraca' => false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $resultados
     * @return array<int, array<string, mixed>>
     */
    private function recalcularMaisBarato(array $resultados): array
    {
        $menorIndice = null;
        $menorPreco = null;

        foreach ($resultados as $indice => $resultado) {
            $preco = (float) ($resultado['preco'] ?? 0);

            if ($preco <= 0) {
                continue;
            }

            if ($menorPreco === null || $preco < $menorPreco) {
                $menorPreco = $preco;
                $menorIndice = $indice;
            }
        }

        if ($menorIndice !== null) {
            $resultados[$menorIndice]['mais_barato'] = true;
        }

        return $resultados;
    }
}
