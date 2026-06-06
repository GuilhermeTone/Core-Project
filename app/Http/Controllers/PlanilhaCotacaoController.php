<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessarPlanilhaCotacaoJob;
use App\Jobs\ProcessarPlanilhaCotacaoItemLojaJob;
use App\Jobs\FinalizarPlanilhaCotacaoJob;
use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Services\CrawlerService;
use App\Services\Planilhas\PlanilhaRevalidacaoService;
use App\Services\Planilhas\XlsxCotacaoService;
use App\Services\ProductEnrichmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
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
            'revalidarUrl' => route('planilhas.revalidar', $planilha),
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
            'revalidar_url' => route('planilhas.revalidar', $planilha),
            'download_url' => $planilha->status === 'concluido' && $planilha->arquivo_processado ? route('planilhas.download', $planilha) : null,
            'itens' => $this->mapearItens($planilha),
        ]);
    }

    public function download(
        PlanilhaCotacao $planilha,
        PlanilhaRevalidacaoService $revalidacao,
        XlsxCotacaoService $xlsx,
    )
    {
        abort_if($planilha->user_id !== auth()->id(), 403);
        abort_if($planilha->status !== 'concluido', 409, 'A planilha ainda está em processamento.');
        abort_if(! $planilha->arquivo_processado || ! Storage::exists($planilha->arquivo_processado), 404);

        $revalidacao->revalidarPlanilha($planilha);
        $arquivoProcessado = $xlsx->gerarPlanilhaProcessada($planilha->fresh('itens'));
        $planilha->update(['arquivo_processado' => $arquivoProcessado]);

        $nomeBase = $planilha->nome ?: pathinfo($planilha->nome_arquivo, PATHINFO_FILENAME);
        $nome = (Str::slug($nomeBase) ?: 'planilha').'-cotada.xlsx';

        return Storage::download($arquivoProcessado, $nome);
    }

    public function revalidar(
        PlanilhaCotacao $planilha,
        PlanilhaRevalidacaoService $revalidacao,
        XlsxCotacaoService $xlsx,
    ) {
        abort_if($planilha->user_id !== auth()->id(), 403);
        abort_if($planilha->status !== 'concluido', 409, 'Aguarde todos os processamentos finalizarem para revalidar.');

        $resumo = $revalidacao->revalidarPlanilha($planilha);
        $arquivoProcessado = $xlsx->gerarPlanilhaProcessada($planilha->fresh('itens'));
        $planilha->update(['arquivo_processado' => $arquivoProcessado]);
        $planilha->load('itens');

        return response()->json([
            'ok' => true,
            'resumo' => $resumo,
            'download_url' => route('planilhas.download', $planilha),
            'itens' => $this->mapearItens($planilha),
        ]);
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
            'preco_loja' => isset($resultado['preco']) ? (float) $resultado['preco'] : null,
            'preco_revalidado' => null,
            'valor_unitario' => $this->aplicarMargem($resultado['preco'] ?? null, $item->margem_percentual),
            'resultado_escolhido' => array_merge($resultado, [
                'preco_original' => $resultado['preco'] ?? null,
                'selecionado_em' => now()->toIso8601String(),
            ]),
            'revalidacao_status' => 'pendente',
            'revalidado_em' => null,
            'revalidacao_mensagem' => null,
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
                ? $this->aplicarMargem($item->preco_loja ?? $resultadoEscolhido['preco'] ?? null, $data['margem_percentual'])
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

    public function refazerBuscaItem(
        Request $request,
        PlanilhaCotacao $planilha,
        PlanilhaCotacaoItem $item,
        CrawlerService $crawler,
    ) {
        abort_if($planilha->user_id !== auth()->id(), 403);
        abort_if($item->planilha_cotacao_id !== $planilha->id, 404);
        abort_if($planilha->status !== 'concluido', 409, 'Aguarde a planilha finalizar para refazer a busca de um item.');

        $data = $request->validate([
            'termo_busca' => ['required', 'string', 'min:2', 'max:500'],
        ]);

        $identificadores = $crawler->getIdentificadores();
        $totalLojas = count($identificadores);
        $termoBusca = trim($data['termo_busca']);

        $item->update([
            'termo_busca' => $termoBusca,
            'status' => 'processando',
            'lojas_total' => $totalLojas,
            'lojas_processadas' => 0,
            'marca_cotada' => null,
            'preco_loja' => null,
            'preco_revalidado' => null,
            'valor_unitario' => null,
            'resultado_escolhido' => null,
            'revalidacao_status' => null,
            'revalidado_em' => null,
            'revalidacao_mensagem' => null,
            'resultados' => [],
            'erro_mensagem' => null,
        ]);

        $itensProcessados = $planilha->itens()
            ->whereKeyNot($item->id)
            ->whereIn('status', ['concluido', 'sem_resultado', 'erro'])
            ->count();

        $planilha->update([
            'status' => 'processando',
            'itens_processados' => $itensProcessados,
            'erro_mensagem' => null,
        ]);

        $jobs = array_map(
            fn (string $identificador) => new ProcessarPlanilhaCotacaoItemLojaJob($item->id, $identificador),
            $identificadores,
        );

        if (empty($jobs)) {
            FinalizarPlanilhaCotacaoJob::dispatch($planilha->id);
        } else {
            $planilhaId = $planilha->id;

            Bus::batch($jobs)
                ->name("Planilha #{$planilha->id} item #{$item->id}")
                ->allowFailures()
                ->then(fn () => FinalizarPlanilhaCotacaoJob::dispatch($planilhaId))
                ->catch(function ($batch, \Throwable $e) use ($planilhaId): void {
                    Log::warning("Planilha #{$planilhaId} teve falha parcial ao refazer item: ".$e->getMessage());
                    FinalizarPlanilhaCotacaoJob::dispatch($planilhaId);
                })
                ->dispatch();
        }

        $planilha->load('itens');

        return response()->json([
            'ok' => true,
            'status' => $planilha->status,
            'total_itens' => $planilha->total_itens,
            'itens_processados' => $planilha->itens_processados,
            'erro_mensagem' => $planilha->erro_mensagem,
            'download_url' => null,
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
            'preco_loja' => null,
            'preco_revalidado' => null,
            'valor_unitario' => null,
            'resultado_escolhido' => null,
            'revalidacao_status' => null,
            'revalidado_em' => null,
            'revalidacao_mensagem' => null,
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
            'termo_busca' => $item->termo_busca,
            'termo_busca_efetivo' => $item->termo_busca ?: $item->descricao,
            'codigos_busca' => ProductEnrichmentService::extrairCodigosDaBusca($item->termo_busca ?: $item->descricao),
            'quantidade' => $item->quantidade,
            'status' => $item->status,
            'lojas_total' => (int) ($item->lojas_total ?? 0),
            'lojas_processadas' => (int) ($item->lojas_processadas ?? 0),
            'marca_cotada' => $item->marca_cotada,
            'preco_loja' => $item->preco_loja !== null ? (float) $item->preco_loja : null,
            'preco_revalidado' => $item->preco_revalidado !== null ? (float) $item->preco_revalidado : null,
            'valor_unitario' => $item->valor_unitario !== null ? (float) $item->valor_unitario : null,
            'margem_percentual' => (float) ($item->margem_percentual ?? 0),
            'resultados' => $item->resultados ?? [],
            'resultado_escolhido' => $item->resultado_escolhido,
            'revalidacao_status' => $item->revalidacao_status,
            'revalidado_em' => $item->revalidado_em?->toIso8601String(),
            'revalidacao_mensagem' => $item->revalidacao_mensagem,
            'erro_mensagem' => $item->erro_mensagem,
            'selecionar_url' => route('planilhas.itens.selecionar', [$item->planilha_cotacao_id, $item->id]),
            'limpar_url' => route('planilhas.itens.limpar', [$item->planilha_cotacao_id, $item->id]),
            'margem_url' => route('planilhas.itens.margem', [$item->planilha_cotacao_id, $item->id]),
            'refazer_busca_url' => route('planilhas.itens.refazer-busca', [$item->planilha_cotacao_id, $item->id]),
            'revalidar_url' => route('planilhas.revalidar', $item->planilha_cotacao_id),
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
