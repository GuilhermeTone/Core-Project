<?php

namespace App\Http\Controllers;

use App\Models\CrawlerExecucao;
use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $hoje = Carbon::today();
        $desde30Dias = Carbon::now()->subDays(30);

        $planilhasBase = PlanilhaCotacao::where('user_id', $userId);
        $itensBase = PlanilhaCotacaoItem::whereHas('planilha', fn ($query) => $query->where('user_id', $userId));

        $stats = [
            'planilhas_total' => (clone $planilhasBase)->count(),
            'planilhas_hoje' => (clone $planilhasBase)->where('created_at', '>=', $hoje)->count(),
            'planilhas_processando' => (clone $planilhasBase)->whereIn('status', ['pendente', 'processando'])->count(),
            'planilhas_concluidas' => (clone $planilhasBase)->where('status', 'concluido')->count(),
            'itens_total' => (clone $itensBase)->count(),
            'itens_sem_resultado' => (clone $itensBase)->where('status', 'sem_resultado')->count(),
            'itens_selecionados' => (clone $itensBase)->whereNotNull('resultado_escolhido')->count(),
            'itens_pendentes_selecao' => (clone $itensBase)
                ->where('status', 'concluido')
                ->whereNull('resultado_escolhido')
                ->whereRaw('JSON_LENGTH(resultados) > 0')
                ->count(),
        ];

        $stats['taxa_selecao'] = $stats['itens_total'] > 0
            ? round(($stats['itens_selecionados'] / $stats['itens_total']) * 100, 1)
            : 0;

        $stats['tempo_medio_processamento'] = $this->tempoMedioProcessamento($userId, $desde30Dias);
        $stats['economia_potencial'] = $this->economiaPotencial($userId, $desde30Dias);

        $statusItens = (clone $itensBase)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $lojas = CrawlerExecucao::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->select(
                'loja_id',
                DB::raw('max(loja_nome) as loja_nome'),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'ok' then 1 else 0 end) as ok_total"),
                DB::raw("sum(case when status = 'erro' then 1 else 0 end) as erros_total"),
                DB::raw("sum(case when status = 'sem_resultado' then 1 else 0 end) as sem_resultado_total"),
                DB::raw('avg(duracao_ms) as duracao_media_ms'),
                DB::raw('max(created_at) as ultima_execucao')
            )
            ->groupBy('loja_id')
            ->orderByDesc('erros_total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $planilhasRecentes = PlanilhaCotacao::withCount('itens')
            ->where('user_id', $userId)
            ->latest()
            ->limit(6)
            ->get();

        $itensAtencao = PlanilhaCotacaoItem::with('planilha')
            ->whereHas('planilha', fn ($query) => $query->where('user_id', $userId))
            ->where(function ($query): void {
                $query->where('status', 'sem_resultado')
                    ->orWhere('revalidacao_status', 'preco_alterado')
                    ->orWhere('revalidacao_status', 'nao_encontrado')
                    ->orWhere('revalidacao_status', 'erro');
            })
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard.index', compact('stats', 'statusItens', 'lojas', 'planilhasRecentes', 'itensAtencao'));
    }

    private function tempoMedioProcessamento(int $userId, Carbon $desde): ?float
    {
        $segundos = PlanilhaCotacao::where('user_id', $userId)
            ->where('status', 'concluido')
            ->where('created_at', '>=', $desde)
            ->selectRaw('avg(timestampdiff(second, created_at, updated_at)) as media')
            ->value('media');

        return $segundos !== null ? round((float) $segundos / 60, 1) : null;
    }

    private function economiaPotencial(int $userId, Carbon $desde): float
    {
        return PlanilhaCotacaoItem::whereHas('planilha', fn ($query) => $query
            ->where('user_id', $userId)
            ->where('created_at', '>=', $desde))
            ->whereNotNull('resultado_escolhido')
            ->whereNotNull('resultados')
            ->get(['resultado_escolhido', 'resultados'])
            ->sum(function (PlanilhaCotacaoItem $item): float {
                $precos = collect($item->resultados ?? [])
                    ->pluck('preco')
                    ->filter(fn ($preco) => is_numeric($preco) && (float) $preco > 0)
                    ->map(fn ($preco) => (float) $preco)
                    ->values();

                if ($precos->count() < 2) {
                    return 0.0;
                }

                $precoEscolhido = (float) ($item->resultado_escolhido['preco'] ?? 0);

                if ($precoEscolhido <= 0) {
                    return 0.0;
                }

                return max(0, $precos->max() - $precoEscolhido);
            });
    }
}
