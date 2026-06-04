<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarPlanilhaCotacaoJob;
use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Services\Planilhas\XlsxCotacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanilhaCotacaoController extends Controller
{
    public function index()
    {
        $planilhas = PlanilhaCotacao::withCount('itens')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('planilhas.index', compact('planilhas'));
    }

    public function store(Request $request, XlsxCotacaoService $xlsx)
    {
        $data = $request->validate([
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('planilha_cotacoes', 'nome')
                    ->where(fn ($query) => $query->where('user_id', auth()->id())),
            ],
            'planilha' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);

        $arquivo = $data['planilha'];
        $path = $arquivo->store('planilhas/originais');

        $planilha = PlanilhaCotacao::create([
            'user_id' => auth()->id(),
            'nome' => trim($data['nome']),
            'nome_arquivo' => $arquivo->getClientOriginalName(),
            'arquivo_original' => $path,
            'status' => 'pendente',
        ]);

        try {
            $itens = $xlsx->lerItens(Storage::path($path));

            foreach ($itens as $item) {
                $planilha->itens()->create($item);
            }

            $planilha->update(['total_itens' => count($itens)]);
            ProcessarPlanilhaCotacaoJob::dispatch($planilha->id);
        } catch (\Throwable $e) {
            $planilha->update([
                'status' => 'erro',
                'erro_mensagem' => $e->getMessage(),
            ]);
        }

        return redirect()->route('planilhas.show', $planilha);
    }

    public function show(PlanilhaCotacao $planilha)
    {
        abort_if($planilha->user_id !== auth()->id(), 403);

        $planilha->load('itens');
        $planilhaInicial = [
            'statusUrl' => route('planilhas.status', $planilha),
            'downloadUrl' => $planilha->status === 'concluido' && $planilha->arquivo_processado ? route('planilhas.download', $planilha) : null,
            'status' => $planilha->status,
            'total' => $planilha->total_itens,
            'processados' => $planilha->itens_processados,
            'erro' => $planilha->erro_mensagem,
            'itens' => $this->mapearItens($planilha),
        ];

        return view('planilhas.show', compact('planilha', 'planilhaInicial'));
    }

    public function status(PlanilhaCotacao $planilha)
    {
        abort_if($planilha->user_id !== auth()->id(), 403);

        $planilha->load('itens');

        return response()->json([
            'status' => $planilha->status,
            'total_itens' => $planilha->total_itens,
            'itens_processados' => $planilha->itens_processados,
            'erro_mensagem' => $planilha->erro_mensagem,
            'download_url' => $planilha->status === 'concluido' && $planilha->arquivo_processado ? route('planilhas.download', $planilha) : null,
            'itens' => $this->mapearItens($planilha),
        ]);
    }

    public function download(PlanilhaCotacao $planilha)
    {
        abort_if($planilha->user_id !== auth()->id(), 403);
        abort_if($planilha->status !== 'concluido', 409, 'A planilha ainda está em processamento.');
        abort_if(! $planilha->arquivo_processado || ! Storage::exists($planilha->arquivo_processado), 404);

        $nomeBase = $planilha->nome ?: pathinfo($planilha->nome_arquivo, PATHINFO_FILENAME);
        $nome = (Str::slug($nomeBase) ?: 'planilha').'-cotada.xlsx';

        return Storage::download($planilha->arquivo_processado, $nome);
    }

    public function selecionarResultado(
        Request $request,
        PlanilhaCotacao $planilha,
        PlanilhaCotacaoItem $item,
        XlsxCotacaoService $xlsx,
    ) {
        abort_if($planilha->user_id !== auth()->id(), 403);
        abort_if($item->planilha_cotacao_id !== $planilha->id, 404);
        abort_if($planilha->status !== 'concluido', 409, 'Aguarde todos os processamentos finalizarem para selecionar itens.');

        $data = $request->validate([
            'resultado_index' => ['required', 'integer', 'min:0'],
        ]);

        $resultados = $item->resultados ?? [];
        $resultado = $resultados[$data['resultado_index']] ?? null;

        abort_if(! $resultado, 422, 'Resultado inválido para este item.');

        $item->update([
            'marca_cotada' => $resultado['marca_detectada'] ?? null,
            'valor_unitario' => $this->aplicarMargem($resultado['preco'] ?? null, $item->margem_percentual),
            'resultado_escolhido' => $resultado,
        ]);

        $arquivoProcessado = $xlsx->gerarPlanilhaProcessada($planilha->fresh('itens'));
        $planilha->update(['arquivo_processado' => $arquivoProcessado]);

        $planilha->load('itens');

        return response()->json([
            'ok' => true,
            'download_url' => route('planilhas.download', $planilha),
            'item' => $this->mapearItem($item->fresh()),
            'itens' => $this->mapearItens($planilha),
        ]);
    }

    public function atualizarMargem(
        Request $request,
        PlanilhaCotacao $planilha,
        PlanilhaCotacaoItem $item,
        XlsxCotacaoService $xlsx,
    ) {
        abort_if($planilha->user_id !== auth()->id(), 403);
        abort_if($item->planilha_cotacao_id !== $planilha->id, 404);
        abort_if($planilha->status !== 'concluido', 409, 'Aguarde todos os processamentos finalizarem para alterar a margem.');

        $data = $request->validate([
            'margem_percentual' => ['required', 'numeric', 'min:0', 'max:999.99'],
        ]);

        $resultadoEscolhido = $item->resultado_escolhido;

        $item->update([
            'margem_percentual' => $data['margem_percentual'],
            'valor_unitario' => $resultadoEscolhido
                ? $this->aplicarMargem($resultadoEscolhido['preco'] ?? null, $data['margem_percentual'])
                : $item->valor_unitario,
        ]);

        $arquivoProcessado = $xlsx->gerarPlanilhaProcessada($planilha->fresh('itens'));
        $planilha->update(['arquivo_processado' => $arquivoProcessado]);

        $planilha->load('itens');

        return response()->json([
            'ok' => true,
            'download_url' => route('planilhas.download', $planilha),
            'item' => $this->mapearItem($item->fresh()),
            'itens' => $this->mapearItens($planilha),
        ]);
    }

    public function limparResultado(PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item, XlsxCotacaoService $xlsx)
    {
        abort_if($planilha->user_id !== auth()->id(), 403);
        abort_if($item->planilha_cotacao_id !== $planilha->id, 404);
        abort_if($planilha->status !== 'concluido', 409, 'Aguarde todos os processamentos finalizarem para remover seleções.');

        $item->update([
            'marca_cotada' => null,
            'valor_unitario' => null,
            'resultado_escolhido' => null,
        ]);

        $arquivoProcessado = $xlsx->gerarPlanilhaProcessada($planilha->fresh('itens'));
        $planilha->update(['arquivo_processado' => $arquivoProcessado]);

        $planilha->load('itens');

        return response()->json([
            'ok' => true,
            'download_url' => route('planilhas.download', $planilha),
            'item' => $this->mapearItem($item->fresh()),
            'itens' => $this->mapearItens($planilha),
        ]);
    }

    public function destroy(PlanilhaCotacao $planilha)
    {
        abort_if($planilha->user_id !== auth()->id(), 403);

        Storage::delete(array_filter([
            $planilha->arquivo_original,
            $planilha->arquivo_processado,
        ]));

        $planilha->delete();

        return redirect()->route('planilhas.index');
    }

    private function mapearItens(PlanilhaCotacao $planilha)
    {
        return $planilha->itens->map(fn ($item): array => $this->mapearItem($item))->values();
    }

    private function mapearItem(PlanilhaCotacaoItem $item): array
    {
        return [
            'id' => $item->id,
            'linha' => $item->linha,
            'descricao' => $item->descricao,
            'quantidade' => $item->quantidade,
            'status' => $item->status,
            'lojas_total' => (int) ($item->lojas_total ?? 0),
            'lojas_processadas' => (int) ($item->lojas_processadas ?? 0),
            'marca_cotada' => $item->marca_cotada,
            'valor_unitario' => $item->valor_unitario !== null ? (float) $item->valor_unitario : null,
            'margem_percentual' => (float) ($item->margem_percentual ?? 0),
            'resultados' => $item->resultados ?? [],
            'resultado_escolhido' => $item->resultado_escolhido,
            'erro_mensagem' => $item->erro_mensagem,
            'selecionar_url' => route('planilhas.itens.selecionar', [$item->planilha_cotacao_id, $item->id]),
            'limpar_url' => route('planilhas.itens.limpar', [$item->planilha_cotacao_id, $item->id]),
            'margem_url' => route('planilhas.itens.margem', [$item->planilha_cotacao_id, $item->id]),
            'aberto' => false,
        ];
    }

    private function aplicarMargem(mixed $preco, mixed $margemPercentual): ?float
    {
        if ($preco === null || $preco === '') {
            return null;
        }

        $preco = (float) $preco;
        $margemPercentual = (float) ($margemPercentual ?? 0);

        return round($preco * (1 + ($margemPercentual / 100)), 2);
    }
}
