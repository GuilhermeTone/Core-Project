<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotação fechada - {{ $planilha->nome ?? $planilha->nome_arquivo }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @php
        $moeda = fn ($valor) => $valor !== null ? 'R$ '.number_format((float) $valor, 2, ',', '.') : 'sem preço';
        $numero = fn ($valor) => rtrim(rtrim(number_format((float) $valor, 3, ',', '.'), '0'), ',');
        $statusClasse = function (?string $status): string {
            return match ($status) {
                'ok' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'preco_alterado' => 'bg-sky-50 text-sky-700 border-sky-200',
                'nao_encontrado' => 'bg-orange-50 text-orange-700 border-orange-200',
                'erro' => 'bg-red-50 text-red-700 border-red-200',
                'pendente' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                default => 'bg-gray-50 text-gray-600 border-gray-200',
            };
        };
        $statusLabel = function (?string $status): string {
            return match ($status) {
                'ok' => 'Preço confirmado',
                'preco_alterado' => 'Preço atualizado',
                'nao_encontrado' => 'Não encontrado',
                'erro' => 'Erro',
                'pendente' => 'Revalidar',
                default => 'Sem revalidação',
            };
        };
    @endphp

    <x-app-header>
        <x-slot:slot>
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-600 text-white text-xl shrink-0">&#128722;</div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-gray-900 truncate">Cotação fechada</h1>
                <p class="text-xs text-gray-500 hidden sm:block">{{ $planilha->nome ?? $planilha->nome_arquivo }}</p>
            </div>
        </x-slot:slot>
        <x-slot:actions>
            <a href="{{ route('planilhas.show', $planilha) }}"
               class="flex items-center gap-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                Voltar para seleção
            </a>
        </x-slot:actions>
    </x-app-header>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-5">
        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Planilha</p>
                    <h2 class="text-lg font-bold text-gray-900 truncate">{{ $planilha->nome ?? $planilha->nome_arquivo }}</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ $planilha->nome_arquivo }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('planilhas.show', $planilha) }}"
                       class="inline-flex justify-center text-sm font-semibold px-4 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
                        Ajustar itens
                    </a>
                    @if ($planilha->arquivo_processado)
                        <a href="{{ route('planilhas.download', $planilha) }}"
                           class="inline-flex justify-center text-sm font-semibold px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors">
                            Baixar planilha
                        </a>
                    @endif
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p class="text-xs text-gray-500">Itens</p>
                    <p class="text-lg font-bold text-gray-900">{{ $resumo['total_itens'] }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs text-emerald-700">Selecionados</p>
                    <p class="text-lg font-bold text-emerald-900">{{ $resumo['selecionados'] }}</p>
                </div>
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <p class="text-xs text-blue-700">Lojas</p>
                    <p class="text-lg font-bold text-blue-900">{{ $resumo['lojas'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                    <p class="text-xs text-gray-500">Compra</p>
                    <p class="text-lg font-bold text-gray-900">{{ $moeda($resumo['total_compra']) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                    <p class="text-xs text-gray-500">Planilha</p>
                    <p class="text-lg font-bold text-gray-900">{{ $moeda($resumo['total_planilha']) }}</p>
                </div>
            </div>

        </section>

        @forelse ($lojas as $loja)
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <header class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">{{ $loja['nome'] }}</h2>
                        <p class="text-xs text-gray-500">{{ $loja['quantidade_itens'] }} item(ns) selecionado(s)</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-3 py-1 font-semibold text-gray-700">
                            Compra {{ $moeda($loja['subtotal_compra']) }}
                        </span>
                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 font-semibold text-emerald-700">
                            Planilha {{ $moeda($loja['subtotal_planilha']) }}
                        </span>
                    </div>
                </header>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left w-20">Linha</th>
                                <th class="px-4 py-2 text-left">Produto</th>
                                <th class="px-4 py-2 text-left w-36">Marca</th>
                                <th class="px-4 py-2 text-right w-28">Qtd.</th>
                                <th class="px-4 py-2 text-right w-32">Compra</th>
                                <th class="px-4 py-2 text-right w-32">Planilha</th>
                                <th class="px-4 py-2 text-center w-36">Status</th>
                                <th class="px-4 py-2 text-center w-24">Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($loja['itens'] as $item)
                                <tr class="border-t border-gray-100 hover:bg-yellow-50">
                                    <td class="px-4 py-3 align-top">
                                        <span class="text-xs font-semibold text-gray-400">Linha {{ $item['linha'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="flex gap-3">
                                            @if ($item['imagem'])
                                                <img src="{{ $item['imagem'] }}" alt="{{ $item['produto'] }}" class="h-10 w-10 rounded border border-gray-200 object-contain bg-white">
                                            @else
                                                <div class="h-10 w-10 rounded border border-gray-200 bg-gray-100"></div>
                                            @endif
                                            <div class="min-w-[16rem]">
                                                <p class="font-semibold text-gray-900">{{ $item['produto'] }}</p>
                                                <p class="text-xs text-gray-500 mt-0.5">{{ $item['descricao'] }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 align-top text-gray-700">{{ $item['marca'] ?? 'sem marca' }}</td>
                                    <td class="px-4 py-3 align-top text-right">
                                        {{ $numero($item['quantidade']) }} {{ $item['unidade'] }}
                                    </td>
                                    <td class="px-4 py-3 align-top text-right">
                                        <p class="font-bold text-gray-900">{{ $moeda($item['preco_compra']) }}</p>
                                        <p class="text-xs text-gray-400">{{ $moeda($item['total_compra']) }} total</p>
                                    </td>
                                    <td class="px-4 py-3 align-top text-right">
                                        <p class="font-bold text-emerald-700">{{ $moeda($item['valor_planilha']) }}</p>
                                        <p class="text-xs text-gray-400">{{ $moeda($item['total_planilha']) }} total</p>
                                    </td>
                                    <td class="px-4 py-3 align-top text-center">
                                        <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold {{ $statusClasse($item['revalidacao_status']) }}">
                                            {{ $statusLabel($item['revalidacao_status']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 align-top text-center">
                                        @if ($item['url'])
                                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener"
                                               class="inline-flex justify-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                                Abrir
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-400">sem link</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center">
                <p class="text-sm font-semibold text-gray-900">Nenhum item selecionado ainda.</p>
                <a href="{{ route('planilhas.show', $planilha) }}"
                   class="mt-4 inline-flex justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Selecionar itens
                </a>
            </section>
        @endforelse

    </main>
</body>
</html>
