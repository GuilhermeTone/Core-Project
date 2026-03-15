<?php

namespace App\Http\Controllers;

use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OrcamentoController extends Controller
{
    public function index()
    {
        $orcamentos = Orcamento::withCount('itens')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
        return view('orcamentos.index', compact('orcamentos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome'          => 'required|string|max:150',
            'cliente'       => 'nullable|string|max:150',
            'observacoes'   => 'nullable|string|max:1000',
            'margem_padrao' => 'nullable|numeric|min:0|max:500',
        ]);

        $data['user_id'] = auth()->id();
        $orcamento = Orcamento::create($data);

        return response()->json(['id' => $orcamento->id, 'nome' => $orcamento->nome]);
    }

    public function show(Orcamento $orcamento)
    {
        abort_if($orcamento->user_id !== auth()->id(), 403);
        $orcamento->load('itens');
        return view('orcamentos.show', compact('orcamento'));
    }

    public function update(Request $request, Orcamento $orcamento)
    {
        abort_if($orcamento->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'nome'          => 'sometimes|string|max:150',
            'cliente'       => 'nullable|string|max:150',
            'observacoes'   => 'nullable|string|max:1000',
            'margem_padrao' => 'nullable|numeric|min:0|max:500',
        ]);

        $orcamento->update($data);
        return response()->json(['ok' => true]);
    }

    public function destroy(Orcamento $orcamento)
    {
        abort_if($orcamento->user_id !== auth()->id(), 403);
        $orcamento->delete();
        return response()->json(['ok' => true]);
    }

    public function addItem(Request $request, Orcamento $orcamento)
    {
        abort_if($orcamento->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'resultado_busca_id' => 'nullable|integer',
            'nome'               => 'required|string|max:255',
            'site'               => 'nullable|string|max:50',
            'preco_custo'        => 'required|numeric|min:0',
            'quantidade'         => 'nullable|integer|min:1',
            'margem'             => 'nullable|numeric|min:0|max:500',
            'url'                => 'nullable|string',
            'imagem'             => 'nullable|string',
        ]);

        $item = $orcamento->itens()->create($data);
        $item->load('orcamento');

        return response()->json([
            'id'          => $item->id,
            'nome'        => $item->nome,
            'site'        => $item->site,
            'preco_custo' => (float) $item->preco_custo,
            'quantidade'  => $item->quantidade,
            'margem'      => $item->margem !== null ? (float) $item->margem : null,
            'preco_venda' => round($item->precoVenda(), 2),
            'url'         => $item->url,
            'imagem'      => $item->imagem,
        ]);
    }

    public function updateItem(Request $request, Orcamento $orcamento, OrcamentoItem $item)
    {
        abort_if($orcamento->user_id !== auth()->id(), 403);

        $data = $request->validate([
            'quantidade' => 'nullable|integer|min:1',
            'margem'     => 'nullable|numeric|min:0|max:500',
        ]);

        $item->update($data);
        $item->load('orcamento');

        return response()->json([
            'preco_venda' => round($item->precoVenda(), 2),
            'total_venda' => round($item->precoVenda() * $item->quantidade, 2),
        ]);
    }

    public function removeItem(Orcamento $orcamento, OrcamentoItem $item)
    {
        abort_if($orcamento->user_id !== auth()->id(), 403);
        $item->delete();
        return response()->json(['ok' => true]);
    }

    public function pdf(Orcamento $orcamento)
    {
        abort_if($orcamento->user_id !== auth()->id(), 403);
        $orcamento->load('itens');
        $pdf = Pdf::loadView('orcamentos.pdf', compact('orcamento'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('orcamento-' . $orcamento->id . '.pdf');
    }

    public function listar()
    {
        $orcamentos = Orcamento::withCount('itens')
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(fn ($o) => ['id' => $o->id, 'nome' => $o->nome, 'itens_count' => $o->itens_count]);

        return response()->json($orcamentos);
    }
}
