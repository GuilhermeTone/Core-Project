<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <x-app-header>
        <x-slot:slot>
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-600 text-white text-sm font-bold shrink-0">DB</div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-xs text-gray-500 hidden sm:block">Acompanhe planilhas, seleções e pontos de atenção</p>
            </div>
        </x-slot:slot>
        <x-slot:actions>
            <a href="{{ route('planilhas.index') }}"
               class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                Nova planilha
            </a>
        </x-slot:actions>
    </x-app-header>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-5">
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Planilhas hoje</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['planilhas_hoje'] }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $stats['planilhas_total'] }} no total</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Processando</p>
                <p class="text-3xl font-bold text-blue-700 mt-2">{{ $stats['planilhas_processando'] }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $stats['planilhas_concluidas'] }} concluída(s)</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Pendentes de seleção</p>
                <p class="text-3xl font-bold text-amber-700 mt-2">{{ $stats['itens_pendentes_selecao'] }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $stats['itens_selecionados'] }} selecionado(s)</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Sem resultado</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['itens_sem_resultado'] }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $stats['taxa_selecao'] }}% dos itens selecionados</p>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900">Resumo de operação</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Itens importados</dt>
                        <dd class="font-semibold text-gray-900">{{ $stats['itens_total'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Tempo médio de planilha</dt>
                        <dd class="font-semibold text-gray-900">
                            {{ $stats['tempo_medio_processamento'] !== null ? number_format($stats['tempo_medio_processamento'], 1, ',', '.') . ' min' : 'sem dados' }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Oportunidade nos selecionados</dt>
                        <dd class="font-semibold text-emerald-700">R$ {{ number_format($stats['economia_potencial'], 2, ',', '.') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 lg:col-span-2">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-gray-900">Status dos itens</h2>
                    <span class="text-xs text-gray-500">{{ $stats['itens_total'] }} item(ns)</span>
                </div>
                <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-2">
                    @foreach (['pendente' => 'Pendente', 'processando' => 'Processando', 'concluido' => 'Concluído', 'sem_resultado' => 'Sem resultado', 'erro' => 'Erro'] as $status => $label)
                        @php($totalStatus = (int) ($statusItens[$status] ?? 0))
                        <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                            <p class="text-xs text-gray-500">{{ $label }}</p>
                            <p class="text-xl font-bold text-gray-900 mt-1">{{ $totalStatus }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900">Planilhas recentes</h2>
                    <a href="{{ route('planilhas.index') }}" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Ver todas</a>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($planilhasRecentes as $planilha)
                        <a href="{{ route('planilhas.show', $planilha) }}" class="block px-5 py-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 truncate">{{ $planilha->nome ?? $planilha->nome_arquivo }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $planilha->itens_count }} item(ns) · {{ $planilha->created_at->diffForHumans() }}</p>
                                </div>
                                <span class="text-xs font-semibold px-2 py-1 rounded-full
                                    @class([
                                        'bg-yellow-50 text-yellow-700 border border-yellow-200' => in_array($planilha->status, ['pendente', 'processando'], true),
                                        'bg-green-50 text-green-700 border border-green-200' => $planilha->status === 'concluido',
                                        'bg-red-50 text-red-700 border border-red-200' => $planilha->status === 'erro',
                                    ])">{{ $planilha->status }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-sm text-gray-500">Nenhuma planilha importada ainda.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-900">Itens que precisam de atenção</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($itensAtencao as $item)
                        <a href="{{ route('planilhas.show', $item->planilha) }}" class="block px-5 py-4 hover:bg-gray-50">
                            <p class="font-semibold text-gray-900 line-clamp-1">{{ $item->descricao }}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $item->planilha->nome ?? $item->planilha->nome_arquivo }} · linha {{ $item->linha }} ·
                                {{ $item->revalidacao_status ?: $item->status }}
                            </p>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-sm text-gray-500">Nenhum item crítico agora.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Lojas nas suas planilhas recentes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3 text-left">Loja</th>
                            <th class="px-5 py-3 text-right">Execuções</th>
                            <th class="px-5 py-3 text-right">Resultados</th>
                            <th class="px-5 py-3 text-right">Sem resultado</th>
                            <th class="px-5 py-3 text-right">Erros</th>
                            <th class="px-5 py-3 text-right">Tempo médio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($lojas as $loja)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-gray-900">{{ $loja->loja_nome ?: $loja->loja_id }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ $loja->total }}</td>
                                <td class="px-5 py-3 text-right text-emerald-700 font-semibold">{{ $loja->ok_total }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ $loja->sem_resultado_total }}</td>
                                <td class="px-5 py-3 text-right text-red-700 font-semibold">{{ $loja->erros_total }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ $loja->duracao_media_ms ? number_format($loja->duracao_media_ms / 1000, 1, ',', '.') . 's' : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">Sem execuções de lojas nos últimos 7 dias.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
