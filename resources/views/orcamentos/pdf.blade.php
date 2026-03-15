<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $orcamento->nome }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; background: #fff; }

        .header { background: #1d4ed8; color: #fff; padding: 24px 30px; margin-bottom: 24px; }
        .header h1 { font-size: 20px; font-weight: bold; margin-bottom: 4px; }
        .header p { font-size: 10px; opacity: 0.85; }
        .header-meta { display: flex; justify-content: space-between; margin-top: 12px; }
        .header-meta div { font-size: 10px; }
        .header-meta strong { display: block; font-size: 12px; }

        .body { padding: 0 30px 30px; }

        .totais { display: flex; gap: 12px; margin-bottom: 20px; }
        .total-card { flex: 1; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 14px; text-align: center; }
        .total-card .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
        .total-card .valor { font-size: 14px; font-weight: bold; }
        .total-card.custo .valor  { color: #1d4ed8; }
        .total-card.venda .valor  { color: #16a34a; }
        .total-card.lucro .valor  { color: #d97706; }

        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        thead tr { background: #f3f4f6; }
        th { text-align: left; padding: 7px 10px; font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; border-bottom: 2px solid #e5e7eb; }
        th.right { text-align: right; }
        th.center { text-align: center; }

        td { padding: 8px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        td.right { text-align: right; }
        td.center { text-align: center; }

        tr:nth-child(even) td { background: #f9fafb; }

        .prod-nome { font-weight: 600; font-size: 11px; }
        .prod-site { font-size: 9px; color: #9ca3af; margin-top: 2px; }

        tfoot tr td { border-top: 2px solid #d1d5db; font-weight: bold; background: #f3f4f6; }
        .total-custo { color: #1d4ed8; }
        .total-venda { color: #16a34a; font-size: 13px; }

        .obs { margin-top: 20px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; background: #fafafa; }
        .obs .title { font-size: 9px; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
        .obs p { font-size: 10px; color: #374151; }

        .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ $orcamento->nome }}</h1>
        <p>Orçamento de ferramentas</p>
        <div class="header-meta">
            <div>
                @if($orcamento->cliente)
                    <strong>{{ $orcamento->cliente }}</strong>
                    Cliente
                @else
                    <strong>—</strong>
                    Sem cliente
                @endif
            </div>
            <div style="text-align:right">
                <strong>{{ $orcamento->created_at->format('d/m/Y') }}</strong>
                Data
            </div>
        </div>
    </div>

    <div class="body">

        {{-- Totais --}}
        @php
            $totalCusto = $orcamento->itens->sum(fn($i) => $i->preco_custo * $i->quantidade);
            $totalVenda = $orcamento->itens->sum(fn($i) => $i->precoVenda() * $i->quantidade);
            $lucro      = $totalVenda - $totalCusto;
        @endphp
        <div class="totais">
            <div class="total-card">
                <div class="label">Itens</div>
                <div class="valor" style="color:#374151">{{ $orcamento->itens->count() }}</div>
            </div>
            <div class="total-card custo">
                <div class="label">Custo Total</div>
                <div class="valor">R$ {{ number_format($totalCusto, 2, ',', '.') }}</div>
            </div>
            <div class="total-card venda">
                <div class="label">Venda Total</div>
                <div class="valor">R$ {{ number_format($totalVenda, 2, ',', '.') }}</div>
            </div>
            <div class="total-card lucro">
                <div class="label">Lucro Estimado</div>
                <div class="valor">R$ {{ number_format($lucro, 2, ',', '.') }}</div>
            </div>
        </div>

        {{-- Tabela de itens --}}
        <table>
            <thead>
                <tr>
                    <th style="width:40%">Produto</th>
                    <th class="right">Custo Unit.</th>
                    <th class="center">Qtd</th>
                    <th class="center">Margem</th>
                    <th class="right">Venda Unit.</th>
                    <th class="right">Total Venda</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orcamento->itens as $item)
                @php
                    $margem      = $item->margem !== null ? $item->margem : $orcamento->margem_padrao;
                    $precoVenda  = $item->precoVenda();
                    $totalItem   = $precoVenda * $item->quantidade;
                @endphp
                <tr>
                    <td>
                        <div class="prod-nome">{{ $item->nome }}</div>
                        @if($item->site)
                            <div class="prod-site">{{ $item->site }}</div>
                        @endif
                    </td>
                    <td class="right">R$ {{ number_format($item->preco_custo, 2, ',', '.') }}</td>
                    <td class="center">{{ $item->quantidade }}</td>
                    <td class="center">{{ number_format($margem, 1, ',', '.') }}%</td>
                    <td class="right">R$ {{ number_format($precoVenda, 2, ',', '.') }}</td>
                    <td class="right">R$ {{ number_format($totalItem, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right;font-size:10px;color:#6b7280">Totais</td>
                    <td class="right total-custo">R$ {{ number_format($totalCusto, 2, ',', '.') }}</td>
                    <td class="right total-venda">R$ {{ number_format($totalVenda, 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        @if($orcamento->observacoes)
        <div class="obs">
            <div class="title">Observações</div>
            <p>{{ $orcamento->observacoes }}</p>
        </div>
        @endif

        <div class="footer">
            Documento gerado em {{ now()->format('d/m/Y \à\s H:i') }} &mdash; Comparador de Ferramentas
        </div>
    </div>

</body>
</html>
