<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Comparador de Ferramentas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        });
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .dots-loader span { animation: blink 1.4s infinite; animation-fill-mode: both; }
        .dots-loader span:nth-child(2) { animation-delay: .2s; }
        .dots-loader span:nth-child(3) { animation-delay: .4s; }
        @keyframes blink { 0%,100%{opacity:.2} 20%{opacity:1} }
        .fade-in { animation: fadeIn .35s ease; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <x-app-header>
        <x-slot:slot>
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-600 text-white text-xl shrink-0">&#128295;</div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Comparador de Ferramentas</h1>
                <p class="text-xs text-gray-500 hidden sm:block">Pesquise em múltiplas lojas ao mesmo tempo</p>
            </div>
        </x-slot:slot>
        <x-slot:actions>
            <a href="{{ route('orcamentos.index') }}"
               class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                &#128203; Orçamentos
            </a>
        </x-slot:actions>
    </x-app-header>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-4"
          x-data="comparador()"
          x-init="init()">

        {{-- ── Formulário ── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
            <form @submit.prevent="buscar()" class="flex gap-3">
                <input
                    type="text"
                    x-model="termo"
                    placeholder="Ex.: chave combinada, alicate universal, chave de fenda..."
                    class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    autocomplete="off"
                />
                <button
                    type="submit"
                    :disabled="enviando || termo.trim().length < 2 || lojasSelecionadas.length === 0"
                    class="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors whitespace-nowrap"
                >
                    <template x-if="!enviando"><span>&#128269; Buscar</span></template>
                    <template x-if="enviando">
                        <span class="flex items-center gap-1.5">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Enviando...
                        </span>
                    </template>
                </button>
            </form>
            <p x-show="erroEnvio" x-cloak class="mt-2 text-xs text-red-600" x-text="erroEnvio"></p>

            {{-- ── Filtro de lojas ── --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Lojas</span>
                    <div class="flex gap-2">
                        <button type="button" @click="selecionarTodasLojas()"
                            class="text-xs text-blue-600 hover:underline">Todas</button>
                        <span class="text-gray-300">|</span>
                        <button type="button" @click="desmarcarTodasLojas()"
                            class="text-xs text-gray-500 hover:underline">Nenhuma</button>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="loja in todasLojas" :key="loja.id">
                        <label class="flex items-center gap-1.5 cursor-pointer select-none
                                      border rounded-full px-3 py-1 text-xs font-medium transition-colors"
                               :class="lojasSelecionadas.includes(loja.id)
                                   ? 'bg-blue-600 text-white border-blue-600'
                                   : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'">
                            <input type="checkbox"
                                   class="sr-only"
                                   :value="loja.id"
                                   :checked="lojasSelecionadas.includes(loja.id)"
                                   @change="toggleLoja(loja.id)">
                            <span x-text="loja.nome"></span>
                        </label>
                    </template>
                </div>
                <p x-show="lojasSelecionadas.length === 0" x-cloak class="mt-1 text-xs text-red-500">
                    Selecione ao menos uma loja.
                </p>
            </div>
        </div>

        {{-- ── Estado vazio ── --}}
        <template x-if="buscas.length === 0">
            <div class="text-center py-16 text-gray-400">
                <div class="text-5xl mb-3">&#128295;</div>
                <p class="text-sm">Nenhuma busca ainda. Comece digitando o nome de uma ferramenta.</p>
            </div>
        </template>

        {{-- ── Cards de busca ── --}}
        <template x-for="busca in buscas" :key="busca.id">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden fade-in">

                {{-- Cabeçalho --}}
                <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div class="flex items-center gap-3 min-w-0">

                        {{-- Ícone de status --}}
                        <template x-if="busca.status === 'concluido'">
                            <span class="shrink-0 w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </template>
                        <template x-if="['pendente','processando'].includes(busca.status)">
                            <span class="shrink-0 w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                <svg class="animate-spin w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </span>
                        </template>
                        <template x-if="busca.status === 'erro'">
                            <span class="shrink-0 w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-red-600 font-bold text-sm">!</span>
                        </template>

                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 capitalize" x-text="busca.termo"></p>
                            <p class="text-xs text-gray-400" x-text="busca.resumo"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 flex-wrap">

                        {{-- Badges de progresso por site (só enquanto buscando) --}}
                        <template x-if="['pendente','processando'].includes(busca.status)">
                            <div class="flex gap-1.5">
                                <template x-for="site in busca.sitesStatus" :key="site.id">
                                    <span :class="site.concluido
                                            ? 'bg-green-100 text-green-700 border-green-300'
                                            : 'bg-yellow-50 text-yellow-600 border-yellow-300'"
                                          class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full border">
                                        <template x-if="site.concluido">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        </template>
                                        <template x-if="!site.concluido">
                                            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        </template>
                                        <span x-text="site.nome"></span>
                                    </span>
                                </template>
                            </div>
                        </template>

                        {{-- Botão abrir/fechar resultados --}}
                        <template x-if="busca.resultados.length > 0">
                            <button
                                @click="busca.aberto = !busca.aberto"
                                :class="busca.aberto ? 'bg-gray-200 text-gray-700' : 'bg-blue-600 text-white hover:bg-blue-700'"
                                class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors"
                                x-text="busca.aberto ? '▲ Fechar' : '▼ Ver ' + busca.resultados.length + ' resultado(s)'"
                            ></button>
                        </template>

                        {{-- Excluir --}}
                        <button @click="excluir(busca)"
                                class="text-xs text-red-400 hover:text-red-600 px-2 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                                title="Excluir">&#128465;</button>
                    </div>
                </div>

                {{-- Mensagem de erro --}}
                <template x-if="busca.status === 'erro'">
                    <div class="px-5 py-3 text-xs text-red-600 bg-red-50 border-t border-red-100">
                        <strong>Erro:</strong> <span x-text="busca.erro_mensagem || 'Falha ao buscar'"></span>
                    </div>
                </template>

                {{-- Tabela de resultados --}}
                <div x-show="busca.aberto && busca.resultados.length > 0" x-cloak>

                    <div class="px-5 py-2 bg-gray-50 border-t border-gray-100 flex flex-wrap gap-2 text-xs items-center">
                        <span class="text-gray-500">Ordenar:</span>
                        <button @click="ordenar(busca,'preco')"
                            :class="busca.ordemCampo==='preco'?'bg-blue-600 text-white':'bg-white text-gray-600 hover:bg-gray-100'"
                            class="px-2.5 py-1 rounded-full border border-gray-200 font-medium transition-colors">
                            Preço <span x-text="busca.ordemCampo==='preco'?(busca.ordemAsc?'↑':'↓'):''"></span>
                        </button>
                        <button @click="ordenar(busca,'nome')"
                            :class="busca.ordemCampo==='nome'?'bg-blue-600 text-white':'bg-white text-gray-600 hover:bg-gray-100'"
                            class="px-2.5 py-1 rounded-full border border-gray-200 font-medium transition-colors">
                            Nome <span x-text="busca.ordemCampo==='nome'?(busca.ordemAsc?'↑':'↓'):''"></span>
                        </button>
                        <button @click="ordenar(busca,'site')"
                            :class="busca.ordemCampo==='site'?'bg-blue-600 text-white':'bg-white text-gray-600 hover:bg-gray-100'"
                            class="px-2.5 py-1 rounded-full border border-gray-200 font-medium transition-colors">
                            Loja <span x-text="busca.ordemCampo==='site'?(busca.ordemAsc?'↑':'↓'):''"></span>
                        </button>
                        <template x-if="['pendente','processando'].includes(busca.status)">
                            <span class="ml-auto text-yellow-600 font-medium">
                                buscando mais sites<span class="dots-loader"><span>.</span><span>.</span><span>.</span></span>
                            </span>
                        </template>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide border-t border-gray-100">
                                    <th class="px-4 py-2 text-left w-14">Img</th>
                                    <th class="px-4 py-2 text-left">Produto</th>
                                    <th class="px-4 py-2 text-left w-28">Loja</th>
                                    <th class="px-4 py-2 text-right w-28">Preço</th>
                                    <th class="px-4 py-2 text-center w-24">Link</th>
                                    <th class="px-4 py-2 text-center w-24">Orçar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in resultadosOrdenados(busca)" :key="item.id">
                                    <tr :class="item.mais_barato
                                                ? 'bg-green-50 border-l-4 border-green-500'
                                                : (idx % 2 === 0 ? 'bg-white' : 'bg-gray-50')"
                                        class="border-b border-gray-100 hover:bg-yellow-50 transition-colors">

                                        <td class="px-4 py-2">
                                            <template x-if="item.imagem">
                                                <img :src="item.imagem" :alt="item.nome"
                                                     class="w-10 h-10 object-contain rounded border border-gray-200 bg-white"
                                                     onerror="this.style.display='none'">
                                            </template>
                                            <template x-if="!item.imagem">
                                                <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center text-gray-400 text-xs">N/A</div>
                                            </template>
                                        </td>

                                        <td class="px-4 py-2">
                                            <div class="flex items-start gap-1.5 flex-wrap">
                                                <template x-if="item.mais_barato">
                                                    <span class="shrink-0 bg-green-100 text-green-800 text-xs font-bold px-1.5 py-0.5 rounded-full border border-green-300">MAIS BARATO</span>
                                                </template>
                                                <span class="text-gray-800 font-medium leading-snug" x-text="item.nome"></span>
                                            </div>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full border"
                                                  :class="siteBadgeClass(item.site)"
                                                  x-text="item.nome_site"></span>
                                        </td>

                                        <td class="px-4 py-2 text-right">
                                            <span :class="item.mais_barato ? 'text-green-700 font-bold text-base' : 'text-gray-800 font-semibold'"
                                                  x-text="item.preco_formatado"></span>
                                        </td>

                                        <td class="px-4 py-2 text-center">
                                            <a :href="item.url" target="_blank" rel="noopener noreferrer"
                                               class="inline-block bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
                                                Comprar ↗
                                            </a>
                                        </td>

                                        <td class="px-4 py-2 text-center">
                                            <button @click="abrirModalOrcamento(item)"
                                                    class="inline-block bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap">
                                                + Orçar
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </template>

        {{-- ── Modal: Adicionar ao Orçamento ── --}}
        <div x-show="modalAberto" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             @keydown.escape.window="fecharModal()">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4 fade-in"
                 @click.stop>

                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-900">Adicionar ao Orçamento</h2>
                    <button @click="fecharModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                </div>

                <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-700 border border-gray-200">
                    <p class="font-medium truncate" x-text="itemSelecionado?.nome"></p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="itemSelecionado?.preco_formatado"></p>
                </div>

                {{-- Quantidade --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Quantidade</label>
                    <input type="number" x-model.number="modalQtd" min="1"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                {{-- Selecionar ou criar orçamento (campo com pesquisa) --}}
                <div x-data="buscaOrcamento()" x-init="iniciar()">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Orçamento</label>

                    <div class="relative">
                        <input
                            type="text"
                            x-model="busca"
                            @focus="aberto = true"
                            @input="aberto = true"
                            @keydown.escape="aberto = false"
                            @keydown.arrow-down.prevent="moverFoco(1)"
                            @keydown.arrow-up.prevent="moverFoco(-1)"
                            @keydown.enter.prevent="selecionarFocado()"
                            :placeholder="selecionado ? selecionado.nome : '🔍 Buscar ou criar orçamento...'"
                            :class="selecionado ? 'font-medium text-gray-900' : 'text-gray-500'"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 pr-8"
                            autocomplete="off"
                        >
                        <template x-if="selecionado">
                            <button type="button" @click="limpar()"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none">
                                &times;
                            </button>
                        </template>

                        <div x-show="aberto && !selecionado" x-cloak
                             @click.outside="aberto = false"
                             class="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                            <button type="button"
                                    @click="criarNovo()"
                                    :class="foco === -1 ? 'bg-green-50 text-green-700' : 'text-gray-600 hover:bg-gray-50'"
                                    class="w-full text-left px-3 py-2.5 text-sm font-medium border-b border-gray-100 flex items-center gap-2">
                                <span class="text-green-600 font-bold">+</span>
                                <span x-text="busca.trim() ? 'Criar &quot;' + busca.trim() + '&quot;' : 'Criar novo orçamento'"></span>
                            </button>
                            <template x-for="(orc, idx) in filtrados()" :key="orc.id">
                                <button type="button"
                                        @click="selecionar(orc)"
                                        :class="foco === idx ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50'"
                                        class="w-full text-left px-3 py-2.5 text-sm flex items-center justify-between gap-2">
                                    <span x-text="orc.nome" class="truncate"></span>
                                    <span class="shrink-0 text-xs text-gray-400" x-text="(orc.itens_count || 0) + ' item(ns)'"></span>
                                </button>
                            </template>
                            <template x-if="filtrados().length === 0 && busca.trim()">
                                <p class="px-3 py-2 text-xs text-gray-400">Nenhum orçamento encontrado. Use + para criar.</p>
                            </template>
                        </div>
                    </div>

                    <div x-show="criandoNovo" x-cloak class="space-y-2 mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-xs font-semibold text-green-700">Novo orçamento</p>
                        <input type="text" x-model="novoNome"
                               placeholder="Nome do orçamento *"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        <input type="text" x-model="novoCliente"
                               placeholder="Cliente (opcional)"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        <button type="button" @click="cancelarNovo()"
                                class="text-xs text-gray-500 hover:underline">Cancelar</button>
                    </div>
                </div>

                <p x-show="modalErro" x-cloak class="text-xs text-red-600" x-text="modalErro"></p>

                <div class="flex gap-2 pt-1">
                    <button @click="fecharModal()"
                            class="flex-1 border border-gray-300 text-gray-600 text-sm font-medium py-2 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button @click="confirmarOrcamento()"
                            :disabled="modalSalvando"
                            class="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-green-300 text-white text-sm font-semibold py-2 rounded-lg transition-colors">
                        <span x-show="!modalSalvando">Adicionar</span>
                        <span x-show="modalSalvando">Salvando...</span>
                    </button>
                </div>
            </div>
        </div>

    </main>

    <footer class="text-center text-xs text-gray-400 py-6">
        Comparador de Ferramentas &mdash; dados coletados em tempo real
    </footer>

    <script>
    const SITE_NOMES = {
        mercadolivre:   'Mercado Livre',
        lojadomecanico: 'Loja do Mecânico',
        anhanguera:     'Anhanguera Ferramentas',
        antferramentas: 'ANT Ferramentas',
        kennedy:        'Ferramentas Kennedy',
        lfmaquinas:     'LF Máquinas',
        martineli:      'Martineli Ferramentas',
        mabore:         'Mabore Ferramentas',
        fermaquinas:    'Fermáquinas',
    };

    const SITE_BADGE_CLASSES = {
        mercadolivre:   'bg-yellow-100 text-yellow-800 border-yellow-300',
        lojadomecanico: 'bg-blue-100 text-blue-800 border-blue-300',
        anhanguera:     'bg-orange-100 text-orange-800 border-orange-300',
        antferramentas: 'bg-red-100 text-red-800 border-red-300',
        kennedy:        'bg-purple-100 text-purple-800 border-purple-300',
        lfmaquinas:     'bg-teal-100 text-teal-800 border-teal-300',
        martineli:      'bg-green-100 text-green-800 border-green-300',
        mabore:         'bg-pink-100 text-pink-800 border-pink-300',
        fermaquinas:    'bg-indigo-100 text-indigo-800 border-indigo-300',
    };

    function mapearResultado(r) {
        return {
            id:              r.id,
            site:            r.site,
            nome_site:       r.nome_site || (SITE_NOMES[r.site] || r.site),
            nome:            r.nome,
            preco:           r.preco,
            preco_formatado: r.preco_formatado || (r.preco ? 'R$ ' + parseFloat(r.preco).toLocaleString('pt-BR', {minimumFractionDigits:2}) : 'Sem preço'),
            url:             r.url,
            imagem:          r.imagem,
            mais_barato:     !!r.mais_barato,
        };
    }

    function montarBusca(raw, preservar) {
        const resultados     = (raw.resultados || []).map(mapearResultado);
        const sitesFound     = [...new Set(resultados.map(r => r.site))];
        const total_sites    = raw.total_sites || 0;

        const sitesStatus = Array.from({ length: Math.max(total_sites, sitesFound.length) }, (_, i) => {
            const site = sitesFound[i];
            return site
                ? { id: site,         nome: SITE_NOMES[site] || site, concluido: true }
                : { id: 'pending_'+i, nome: 'Buscando...',             concluido: false };
        });

        const total = resultados.length;
        let resumo  = raw.criado_em || '';
        if      (raw.status === 'concluido')    resumo += ` • ${total} resultado(s)`;
        else if (raw.status === 'processando')  resumo += ` • ${raw.sites_concluidos || 0} de ${total_sites || '?'} site(s)`;
        else if (raw.status === 'pendente')     resumo += ' • na fila...';
        else if (raw.status === 'erro')         resumo += ' • erro';

        const jaAbriu = preservar?._jaAbriuAuto || false;
        const aberto  = preservar ? preservar.aberto : false;

        return {
            id:              raw.id,
            termo:           raw.termo,
            status:          raw.status,
            erro_mensagem:   raw.erro_mensagem,
            total_sites,
            sites_concluidos: raw.sites_concluidos || sitesFound.length,
            resultados,
            sitesStatus,
            resumo,
            aberto,
            _jaAbriuAuto:    jaAbriu || (raw.status === 'concluido'),
            ordemCampo:      preservar?.ordemCampo  || 'preco',
            ordemAsc:        preservar?.ordemAsc    ?? true,
            criado_em:       raw.criado_em || preservar?.criado_em || '',
        };
    }

    function siteBadgeClass(site) {
        return SITE_BADGE_CLASSES[site] || 'bg-gray-100 text-gray-700 border-gray-300';
    }

    function comparador() {
        return {
            termo:              '',
            enviando:           false,
            erroEnvio:          null,
            buscas:             [],
            polling:            null,
            todasLojas:         @json($listaLojas),
            lojasSelecionadas:  @json($listaLojas).map(l => l.id),

            // Modal de orçamento
            modalAberto:     false,
            itemSelecionado: null,
            modalQtd:        1,
            modalErro:       null,
            modalSalvando:   false,
            orcamentos:      [],

            init() {
                const iniciais = @json($buscasJson);
                this.buscas = iniciais.map(b => montarBusca(b, null));
                this.polling = setInterval(() => this.pollAtivas(), 2000);
                this.carregarOrcamentos();
            },

            async carregarOrcamentos() {
                try {
                    const res = await axios.get('/orcamentos/listar');
                    this.orcamentos = res.data;
                    window._orcamentosGlobal = this.orcamentos;
                } catch (_) {}
            },

            toggleLoja(id) {
                if (this.lojasSelecionadas.includes(id)) {
                    this.lojasSelecionadas = this.lojasSelecionadas.filter(l => l !== id);
                } else {
                    this.lojasSelecionadas.push(id);
                }
            },

            selecionarTodasLojas() {
                this.lojasSelecionadas = this.todasLojas.map(l => l.id);
            },

            desmarcarTodasLojas() {
                this.lojasSelecionadas = [];
            },

            async buscar() {
                if (this.termo.trim().length < 2) return;
                if (this.lojasSelecionadas.length === 0) return;
                this.enviando  = true;
                this.erroEnvio = null;
                const termoEnviado = this.termo.trim();

                try {
                    const res = await axios.post('/ferramentas/buscar', {
                        termo: termoEnviado,
                        lojas: this.lojasSelecionadas,
                    });
                    this.buscas.unshift(montarBusca({
                        id:        res.data.busca_id,
                        termo:     termoEnviado,
                        status:    'pendente',
                        total_sites: 0,
                        resultados: [],
                        criado_em: 'agora',
                    }, null));
                    this.termo = '';
                } catch (e) {
                    this.erroEnvio = e.response?.data?.errors
                        ? Object.values(e.response.data.errors).flat().join(' ')
                        : 'Erro ao enviar. Tente novamente.';
                } finally {
                    this.enviando = false;
                }
            },

            async pollAtivas() {
                const ativas = this.buscas.filter(b => ['pendente','processando'].includes(b.status));
                await Promise.all(ativas.map(b => this.atualizar(b)));
            },

            async atualizar(busca) {
                try {
                    const res  = await axios.get(`/ferramentas/${busca.id}/status`);
                    const idx  = this.buscas.findIndex(b => b.id === busca.id);
                    if (idx === -1) return;
                    const atual = this.buscas[idx];
                    this.buscas[idx] = montarBusca({ ...res.data, id: busca.id, criado_em: busca.criado_em }, atual);
                } catch (_) { /* ignora */ }
            },

            ordenar(busca, campo) {
                busca.ordemAsc = busca.ordemCampo === campo ? !busca.ordemAsc : true;
                busca.ordemCampo = campo;
            },

            resultadosOrdenados(busca) {
                return [...busca.resultados].sort((a, b) => {
                    let va = a[busca.ordemCampo], vb = b[busca.ordemCampo];
                    if (busca.ordemCampo === 'preco') {
                        va = va == null ? Infinity : parseFloat(va);
                        vb = vb == null ? Infinity : parseFloat(vb);
                    } else {
                        va = (va||'').toLowerCase(); vb = (vb||'').toLowerCase();
                    }
                    return va < vb ? (busca.ordemAsc ? -1 : 1) : va > vb ? (busca.ordemAsc ? 1 : -1) : 0;
                });
            },

            async excluir(busca) {
                if (!confirm(`Excluir busca "${busca.termo}"?`)) return;
                try {
                    await axios.delete(`/ferramentas/${busca.id}`);
                    this.buscas = this.buscas.filter(b => b.id !== busca.id);
                } catch (_) { alert('Não foi possível excluir.'); }
            },

            abrirModalOrcamento(item) {
                this.itemSelecionado = item;
                this.modalQtd        = 1;
                this.modalErro       = null;
                this.modalSalvando   = false;
                this.modalAberto     = true;
                // Dispara reset no componente filho buscaOrcamento
                window.dispatchEvent(new CustomEvent('reset-busca-orcamento'));
            },

            fecharModal() {
                this.modalAberto     = false;
                this.itemSelecionado = null;
            },

            async confirmarOrcamento() {
                this.modalErro = null;
                if (!this.itemSelecionado) return;
                if (this.modalQtd < 1) { this.modalErro = 'Quantidade inválida.'; return; }

                // Coleta dados do componente filho via evento
                const ev = new CustomEvent('get-orcamento-selecionado', { detail: {} });
                window.dispatchEvent(ev);
                // Aguarda resposta via variável global temporária
                await new Promise(r => setTimeout(r, 0));
                const dados = window._orcamentoSelecionado || {};

                const { orcId, novoNome, novoCliente, criandoNovo } = dados;

                if (!orcId && !criandoNovo) {
                    this.modalErro = 'Selecione ou crie um orçamento.';
                    return;
                }
                if (criandoNovo && !novoNome?.trim()) {
                    this.modalErro = 'Informe um nome para o orçamento.';
                    return;
                }

                this.modalSalvando = true;
                try {
                    let idFinal = orcId;

                    if (criandoNovo) {
                        const resOrc = await axios.post('/orcamentos', {
                            nome:    novoNome.trim(),
                            cliente: novoCliente?.trim() || null,
                        });
                        idFinal = resOrc.data.id;
                        this.orcamentos.unshift({ id: idFinal, nome: resOrc.data.nome, itens_count: 0 });
                    }

                    await axios.post(`/orcamentos/${idFinal}/itens`, {
                        resultado_busca_id: this.itemSelecionado.id,
                        nome:        this.itemSelecionado.nome,
                        site:        this.itemSelecionado.site,
                        preco_custo: this.itemSelecionado.preco,
                        quantidade:  this.modalQtd,
                        url:         this.itemSelecionado.url,
                        imagem:      this.itemSelecionado.imagem,
                    });

                    // Incrementa contador no componente filho
                    window.dispatchEvent(new CustomEvent('item-adicionado', { detail: { id: idFinal } }));
                    this.fecharModal();
                    alert('Item adicionado ao orçamento!');
                } catch (e) {
                    this.modalErro = e.response?.data?.message || 'Erro ao salvar.';
                } finally {
                    this.modalSalvando = false;
                }
            },
        };
    }

    function buscaOrcamento() {
        return {
            busca:       '',
            aberto:      false,
            foco:        0,
            selecionado: null,
            criandoNovo: false,
            novoNome:    '',
            novoCliente: '',
            _lista:      [],

            iniciar() {
                // Recebe lista de orçamentos do componente pai via evento
                this._lista = Alpine.store ? [] : [];
                // Usa a lista global
                this._lista = window._orcamentosGlobal || [];

                window.addEventListener('reset-busca-orcamento', () => {
                    this.busca       = '';
                    this.aberto      = false;
                    this.foco        = 0;
                    this.selecionado = null;
                    this.criandoNovo = false;
                    this.novoNome    = '';
                    this.novoCliente = '';
                    this._lista      = window._orcamentosGlobal || [];
                });

                window.addEventListener('get-orcamento-selecionado', () => {
                    window._orcamentoSelecionado = {
                        orcId:       this.selecionado?.id || null,
                        criandoNovo: this.criandoNovo,
                        novoNome:    this.novoNome,
                        novoCliente: this.novoCliente,
                    };
                });

                window.addEventListener('item-adicionado', (e) => {
                    const orc = this._lista.find(o => o.id === e.detail.id);
                    if (orc) orc.itens_count = (orc.itens_count || 0) + 1;
                });
            },

            filtrados() {
                const q = this.busca.trim().toLowerCase();
                return this._lista.filter(o => !q || o.nome.toLowerCase().includes(q));
            },

            selecionar(orc) {
                this.selecionado = orc;
                this.criandoNovo = false;
                this.busca       = '';
                this.aberto      = false;
            },

            limpar() {
                this.selecionado = null;
                this.busca       = '';
                this.aberto      = true;
            },

            criarNovo() {
                this.criandoNovo = true;
                this.novoNome    = this.busca.trim();
                this.selecionado = null;
                this.aberto      = false;
                this.busca       = '';
            },

            cancelarNovo() {
                this.criandoNovo = false;
                this.novoNome    = '';
                this.novoCliente = '';
            },

            moverFoco(dir) {
                const max = this.filtrados().length - 1;
                this.foco = Math.max(-1, Math.min(max, this.foco + dir));
            },

            selecionarFocado() {
                if (this.foco === -1) { this.criarNovo(); return; }
                const item = this.filtrados()[this.foco];
                if (item) this.selecionar(item);
            },
        };
    }
    </script>
</body>
</html>
