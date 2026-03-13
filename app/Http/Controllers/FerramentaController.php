<?php

namespace App\Http\Controllers;

use App\Jobs\BuscarFerramentaJob;
use App\Models\FerramentaBusca;
use App\Models\ResultadoBusca;
use App\Services\CrawlerService;
use Illuminate\Http\Request;

class FerramentaController extends Controller
{
    public function index(CrawlerService $crawler)
    {
        $listaLojas = $crawler->getListaLojas();

        $buscasRecentes = FerramentaBusca::with(['resultados' => fn ($q) => $q->orderByRaw('mais_barato DESC')->orderBy('preco')])
            ->latest()
            ->limit(20)
            ->get();

        $buscasJson = $buscasRecentes->map(fn (FerramentaBusca $b) => [
            'id'               => $b->id,
            'termo'            => $b->termo,
            'status'           => $b->status,
            'erro_mensagem'    => $b->erro_mensagem,
            'total_sites'      => $b->total_sites,
            'sites_concluidos' => $b->resultados->pluck('site')->unique()->count(),
            'criado_em'        => $b->created_at->diffForHumans(),
            'resultados'       => $b->resultados->map(fn (ResultadoBusca $r) => [
                'id'              => $r->id,
                'site'            => $r->site,
                'nome_site'       => $r->nome_site,
                'nome'            => $r->nome,
                'preco'           => $r->preco,
                'preco_formatado' => $r->preco ? 'R$ ' . number_format((float) $r->preco, 2, ',', '.') : 'Sem preço',
                'url'             => $r->url,
                'imagem'          => $r->imagem,
                'mais_barato'     => (bool) $r->mais_barato,
            ])->values()->all(),
        ])->values()->all();

        return view('ferramentas.index', compact('buscasRecentes', 'buscasJson', 'listaLojas'));
    }

    public function buscar(Request $request, CrawlerService $crawler)
    {
        $request->validate([
            'termo' => 'required|string|min:2|max:100',
            'lojas' => 'nullable|array',
            'lojas.*' => 'string',
        ]);

        $lojas = $request->lojas ?? null;

        // Valida que os identificadores informados existem
        if (!empty($lojas)) {
            $validos = $crawler->getIdentificadores();
            $lojas   = array_values(array_intersect($lojas, $validos));
            if (empty($lojas)) {
                $lojas = null; // se nenhum válido, usa todos
            }
        }

        $busca = FerramentaBusca::create([
            'termo' => $request->termo,
            'lojas' => $lojas,
        ]);

        BuscarFerramentaJob::dispatch($busca->id);

        return response()->json([
            'busca_id' => $busca->id,
            'status'   => $busca->status,
            'message'  => 'Busca iniciada',
        ]);
    }

    public function status(int $id)
    {
        $busca = FerramentaBusca::with(['resultados' => function ($q) {
            $q->orderByRaw('mais_barato DESC')->orderBy('preco');
        }])->findOrFail($id);

        $sitesEncontrados = $busca->resultados
            ->pluck('site')
            ->unique()
            ->values()
            ->toArray();

        return response()->json([
            'status'           => $busca->status,
            'erro_mensagem'    => $busca->erro_mensagem,
            'termo'            => $busca->termo,
            'total_sites'      => $busca->total_sites,
            'sites_concluidos' => count($sitesEncontrados),
            'total'            => $busca->resultados->count(),
            'resultados'       => $busca->resultados->map(fn (ResultadoBusca $r) => [
                'id'              => $r->id,
                'site'            => $r->site,
                'nome_site'       => $r->nome_site,
                'nome'            => $r->nome,
                'descricao'       => $r->descricao,
                'preco'           => $r->preco,
                'preco_formatado' => $r->preco ? 'R$ ' . number_format((float) $r->preco, 2, ',', '.') : 'Sem preço',
                'url'             => $r->url,
                'imagem'          => $r->imagem,
                'mais_barato'     => (bool) $r->mais_barato,
            ]),
        ]);
    }

    public function destroy(int $id)
    {
        FerramentaBusca::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }
}
