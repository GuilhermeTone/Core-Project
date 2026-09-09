<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saúde das Lojas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <x-app-header>
        <x-slot:slot>
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-slate-800 text-white text-sm font-bold shrink-0">ADM</div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Saúde das lojas</h1>
                <p class="text-xs text-gray-500 hidden sm:block">Monitore crawlers, falhas e desempenho das buscas</p>
            </div>
        </x-slot:slot>
        <x-slot:actions>
            <a href="{{ route('dashboard.index') }}"
               class="flex items-center gap-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                Dashboard
            </a>
        </x-slot:actions>
    </x-app-header>

    <main class="max-w-6xl mx-auto px-4 py-6 space-y-5">
        <section class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Lojas</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">{{ $resumo['lojas_total'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Saudáveis</p>
                <p class="text-3xl font-bold text-emerald-700 mt-2">{{ $resumo['saudaveis'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Atenção</p>
                <p class="text-3xl font-bold text-amber-700 mt-2">{{ $resumo['atencao'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Críticas</p>
                <p class="text-3xl font-bold text-red-700 mt-2">{{ $resumo['criticas'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Sem dados</p>
                <p class="text-3xl font-bold text-gray-700 mt-2">{{ $resumo['sem_dados'] }}</p>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <h2 class="font-semibold text-gray-900">Status por loja nas últimas 24h</h2>
                <span class="text-xs text-gray-500">Ordenado por severidade</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3 text-left">Loja</th>
                            <th class="px-5 py-3 text-left">Status</th>
                            <th class="px-5 py-3 text-right">Execuções</th>
                            <th class="px-5 py-3 text-right">Resultados</th>
                            <th class="px-5 py-3 text-right">Sem resultado</th>
                            <th class="px-5 py-3 text-right">Erros</th>
                            <th class="px-5 py-3 text-right">Erro %</th>
                            <th class="px-5 py-3 text-right">Tempo médio</th>
                            <th class="px-5 py-3 text-right">Última</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($lojas as $loja)
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-gray-900">{{ $loja['nome'] }}</p>
                                    <p class="text-xs text-gray-400">{{ $loja['id'] }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex text-xs font-semibold px-2 py-1 rounded-full border
                                        @class([
                                            'bg-green-50 text-green-700 border-green-200' => $loja['status'] === 'saudavel',
                                            'bg-amber-50 text-amber-700 border-amber-200' => $loja['status'] === 'atencao',
                                            'bg-red-50 text-red-700 border-red-200' => $loja['status'] === 'critico',
                                            'bg-gray-100 text-gray-600 border-gray-200' => $loja['status'] === 'sem_dados',
                                        ])">
                                        {{ [
                                            'saudavel' => 'Saudável',
                                            'atencao' => 'Atenção',
                                            'critico' => 'Crítico',
                                            'sem_dados' => 'Sem dados',
                                        ][$loja['status']] }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ $loja['total_24h'] }}</td>
                                <td class="px-5 py-3 text-right text-emerald-700 font-semibold">{{ $loja['ok_24h'] }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ $loja['sem_resultado_24h'] }}</td>
                                <td class="px-5 py-3 text-right text-red-700 font-semibold">{{ $loja['erros_24h'] }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ $loja['taxa_erro_24h'] !== null ? number_format($loja['taxa_erro_24h'], 1, ',', '.') . '%' : '-' }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ $loja['duracao_media_ms'] ? number_format($loja['duracao_media_ms'] / 1000, 1, ',', '.') . 's' : '-' }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">
                                    {{ $loja['ultima_execucao'] ? \Illuminate\Support\Carbon::parse($loja['ultima_execucao'])->diffForHumans() : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Últimas falhas</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($ultimosErros as $erro)
                    <div class="px-5 py-4">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900">{{ $erro->loja_nome ?: $erro->loja_id }}</p>
                                <p class="text-xs text-gray-500 mt-1 truncate">{{ $erro->termo }}</p>
                            </div>
                            <span class="text-xs text-gray-400 shrink-0">{{ $erro->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg px-3 py-2 mt-3 break-words">
                            {{ $erro->erro_mensagem }}
                        </p>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-gray-500">Nenhuma falha registrada ainda.</p>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
