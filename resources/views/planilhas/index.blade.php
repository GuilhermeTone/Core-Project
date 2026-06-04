<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Planilhas de Cotação</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <x-app-header>
        <x-slot:slot>
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-600 text-white text-xl shrink-0">&#128196;</div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Planilhas de Cotação</h1>
                <p class="text-xs text-gray-500 hidden sm:block">Importe, pesquise e exporte no padrão Comlink</p>
            </div>
        </x-slot:slot>
        <x-slot:actions>
            <a href="{{ route('ferramentas.index') }}"
               class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                &#128269; Buscas específicas
            </a>
        </x-slot:actions>
    </x-app-header>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-5">
        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <form action="{{ route('planilhas.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-[1fr_1fr_auto] gap-3 lg:items-end">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Nome da planilha</label>
                    <input type="text"
                           name="nome"
                           value="{{ old('nome') }}"
                           maxlength="255"
                           placeholder="Ex.: Cotação 455691"
                           class="block w-full text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    @error('nome')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Arquivo XLSX</label>
                    <input type="file" name="planilha" accept=".xlsx"
                           class="block w-full text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    @error('planilha')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                    Importar planilha
                </button>
            </form>
        </section>

        <section class="space-y-3">
            @forelse ($planilhas as $planilha)
                <article class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full
                                @class([
                                    'bg-yellow-400' => in_array($planilha->status, ['pendente', 'processando'], true),
                                    'bg-green-500' => $planilha->status === 'concluido',
                                    'bg-red-500' => $planilha->status === 'erro',
                                ])"></span>
                            <h2 class="font-semibold text-gray-900 truncate">{{ $planilha->nome ?? $planilha->nome_arquivo }}</h2>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $planilha->nome_arquivo }} · {{ $planilha->created_at->diffForHumans() }} · {{ $planilha->itens_count }} item(ns) ·
                            {{ $planilha->itens_processados }}/{{ $planilha->total_itens }} processado(s)
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('planilhas.show', $planilha) }}"
                           class="text-sm font-semibold px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                            Abrir
                        </a>
                        @if ($planilha->arquivo_processado)
                            <a href="{{ route('planilhas.download', $planilha) }}"
                               class="text-sm font-semibold px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors">
                                Download
                            </a>
                        @endif
                        <form action="{{ route('planilhas.destroy', $planilha) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm px-3 py-2 rounded-lg border border-gray-200 text-gray-500 hover:text-red-600 hover:border-red-200 hover:bg-red-50">
                                Excluir
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="text-center py-16 text-gray-400">
                    <div class="text-5xl mb-3">&#128196;</div>
                    <p class="text-sm">Nenhuma planilha importada ainda.</p>
                </div>
            @endforelse
        </section>
    </main>
</body>
</html>
