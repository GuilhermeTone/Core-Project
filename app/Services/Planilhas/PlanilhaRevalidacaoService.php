<?php

namespace App\Services\Planilhas;

use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Services\CrawlerService;
use App\Services\ProductEnrichmentService;
use Illuminate\Support\Carbon;

class PlanilhaRevalidacaoService
{
    public function __construct(private readonly CrawlerService $crawler) {}

    /**
     * @return array{total: int, ok: int, preco_alterado: int, nao_encontrado: int, erro: int}
     */
    public function revalidarPlanilha(PlanilhaCotacao $planilha): array
    {
        $resumo = [
            'total' => 0,
            'ok' => 0,
            'preco_alterado' => 0,
            'nao_encontrado' => 0,
            'erro' => 0,
        ];

        $planilha->itens()
            ->whereNotNull('resultado_escolhido')
            ->get()
            ->each(function (PlanilhaCotacaoItem $item) use (&$resumo): void {
                $status = $this->revalidarItem($item)['status'];
                $resumo['total']++;

                if (isset($resumo[$status])) {
                    $resumo[$status]++;
                }
            });

        return $resumo;
    }

    /**
     * @return array{status: string, mensagem: string|null}
     */
    public function revalidarItem(PlanilhaCotacaoItem $item): array
    {
        $resultadoEscolhido = $item->resultado_escolhido;

        if (empty($resultadoEscolhido)) {
            return ['status' => 'sem_selecao', 'mensagem' => 'Item sem resultado selecionado.'];
        }

        $site = (string) ($resultadoEscolhido['site'] ?? '');
        if ($site === '') {
            return $this->marcarErro($item, 'Resultado selecionado sem loja de origem.');
        }

        try {
            $resultados = $this->crawler->buscarEmLoja($item->descricao, $site);
            $resultadoAtual = $this->encontrarMesmoProduto($resultadoEscolhido, $resultados);

            if ($resultadoAtual === null) {
                return $this->marcarNaoEncontrado($item, 'Produto selecionado não apareceu novamente na busca da loja.');
            }

            $precoAtual = isset($resultadoAtual['preco']) ? (float) $resultadoAtual['preco'] : null;
            if ($precoAtual === null || $precoAtual <= 0) {
                return $this->marcarNaoEncontrado($item, 'Produto encontrado novamente, mas sem preço válido.');
            }

            $precoAnterior = (float) ($item->preco_loja ?? $resultadoEscolhido['preco'] ?? $precoAtual);
            $status = abs($precoAtual - $precoAnterior) <= 0.01 ? 'ok' : 'preco_alterado';
            $mensagem = $status === 'ok'
                ? 'Preço revalidado na loja.'
                : 'Preço da loja mudou de '.$this->formatarPreco($precoAnterior).' para '.$this->formatarPreco($precoAtual).'.';

            $resultadoAtualizado = array_merge($resultadoEscolhido, [
                'preco' => $precoAtual,
                'preco_original' => $resultadoEscolhido['preco_original'] ?? ($resultadoEscolhido['preco'] ?? null),
                'preco_revalidado' => $precoAtual,
                'revalidacao_status' => $status,
                'revalidado_em' => Carbon::now()->toIso8601String(),
                'revalidacao_mensagem' => $mensagem,
            ]);

            $item->update([
                'marca_cotada' => $resultadoAtual['marca_detectada'] ?? $item->marca_cotada,
                'preco_loja' => $precoAtual,
                'preco_revalidado' => $precoAtual,
                'valor_unitario' => $this->aplicarMargem($precoAtual, $item->margem_percentual),
                'resultado_escolhido' => $resultadoAtualizado,
                'revalidacao_status' => $status,
                'revalidado_em' => Carbon::now(),
                'revalidacao_mensagem' => $mensagem,
            ]);

            return ['status' => $status, 'mensagem' => $mensagem];
        } catch (\Throwable $e) {
            return $this->marcarErro($item, $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $resultadoEscolhido
     * @param  array<int, array<string, mixed>>  $resultadosAtuais
     * @return array<string, mixed>|null
     */
    private function encontrarMesmoProduto(array $resultadoEscolhido, array $resultadosAtuais): ?array
    {
        $urlEscolhida = $this->normalizarUrl((string) ($resultadoEscolhido['url'] ?? ''));
        $nomeEscolhido = ProductEnrichmentService::normalizarTexto((string) ($resultadoEscolhido['nome'] ?? ''));
        $melhor = null;
        $melhorScore = 0.0;

        foreach ($resultadosAtuais as $resultadoAtual) {
            $urlAtual = $this->normalizarUrl((string) ($resultadoAtual['url'] ?? ''));

            if ($urlEscolhida !== '' && $urlAtual === $urlEscolhida) {
                return $resultadoAtual;
            }

            $nomeAtual = ProductEnrichmentService::normalizarTexto((string) ($resultadoAtual['nome'] ?? ''));
            similar_text($nomeEscolhido, $nomeAtual, $similaridade);

            $scoreProduto = (float) ($resultadoAtual['score_produto'] ?? 0);
            $score = ($similaridade / 100) * 0.70 + $scoreProduto * 0.30;

            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $resultadoAtual;
            }
        }

        return $melhorScore >= 0.78 ? $melhor : null;
    }

    private function marcarNaoEncontrado(PlanilhaCotacaoItem $item, string $mensagem): array
    {
        $item->update([
            'revalidacao_status' => 'nao_encontrado',
            'revalidado_em' => Carbon::now(),
            'revalidacao_mensagem' => $mensagem,
        ]);

        return ['status' => 'nao_encontrado', 'mensagem' => $mensagem];
    }

    private function marcarErro(PlanilhaCotacaoItem $item, string $mensagem): array
    {
        $item->update([
            'revalidacao_status' => 'erro',
            'revalidado_em' => Carbon::now(),
            'revalidacao_mensagem' => $mensagem,
        ]);

        return ['status' => 'erro', 'mensagem' => $mensagem];
    }

    private function aplicarMargem(mixed $preco, mixed $margemPercentual): ?float
    {
        if ($preco === null || $preco === '') {
            return null;
        }

        return round((float) $preco * (1 + ((float) ($margemPercentual ?? 0) / 100)), 2);
    }

    private function normalizarUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $partes = parse_url($url);
        $host = strtolower((string) ($partes['host'] ?? ''));
        $path = rtrim((string) ($partes['path'] ?? ''), '/');

        return $host.$path;
    }

    private function formatarPreco(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
