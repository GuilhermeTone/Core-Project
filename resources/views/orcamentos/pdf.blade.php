<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $orcamento->nome }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; background: #fff; }

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
        .total-card.total .valor  { color: #16a34a; font-size: 16px; }

        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        thead tr { background: #f3f4f6; }
        th { text-align: left; padding: 7px 10px; font-size: 9px; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; border-bottom: 2px solid #e5e7eb; }
        th.right { text-align: right; }
        th.center { text-align: center; }

        td { padding: 9px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; font-size: 10.5px; line-height: 1.35; }
        td.right { text-align: right; }
        td.center { text-align: center; }

        tr:nth-child(even) td { background: #f9fafb; }

        .prod-nome { font-family: Helvetica, DejaVu Sans, sans-serif; font-weight: bold; font-size: 10.8px; color: #111827; line-height: 1.35; }
        .prod-site { font-size: 8.5px; color: #9ca3af; margin-top: 3px; text-transform: uppercase; letter-spacing: .3px; }

        tfoot tr td { border-top: 2px solid #d1d5db; font-weight: bold; background: #f3f4f6; }
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
            $totalVenda = $orcamento->itens->sum(fn($i) => $i->precoVenda() * $i->quantidade);
        @endphp
        <div class="totais">
            <div class="total-card">
                <div class="label">Itens</div>
                <div class="valor" style="color:#374151">{{ $orcamento->itens->count() }}</div>
            </div>
            <div class="total-card total">
                <div class="label">Valor Total</div>
                <div class="valor">R$ {{ number_format($totalVenda, 2, ',', '.') }}</div>
            </div>
        </div>

        {{-- Tabela de itens --}}
        <table>
            <thead>
                <tr>
                    <th style="width:55%">Produto</th>
                    <th class="center">Qtd</th>
                    <th class="right">Valor Unit.</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orcamento->itens as $item)
                @php
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
                    <td class="center">{{ $item->quantidade }}</td>
                    <td class="right">R$ {{ number_format($precoVenda, 2, ',', '.') }}</td>
                    <td class="right">R$ {{ number_format($totalItem, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;font-size:10px;color:#6b7280">Total do orçamento</td>
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
