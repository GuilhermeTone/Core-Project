<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $orcamento->nome }}</title>
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
        .fade-in { animation: fadeIn .3s ease; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <x-app-header>
        <x-slot:slot>
            <a href="{{ route('orcamentos.index') }}"
               class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 transition-colors text-lg shrink-0">
                &#8592;
            </a>
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-gray-900 truncate">{{ $orcamento->nome }}</h1>
                <p class="text-xs text-gray-500">
                    @if($orcamento->cliente) {{ $orcamento->cliente }} &mdash; @endif
                    {{ $orcamento->created_at->format('d/m/Y') }}
                </p>
            </div>
        </x-slot:slot>
        <x-slot:actions>
            <a href="{{ route('orcamentos.pdf', $orcamento) }}" target="_blank"
               class="flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                &#128196; <span class="hidden sm:inline">Gerar PDF</span><span class="sm:hidden">PDF</span>
            </a>
        </x-slot:actions>
    </x-app-header>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-4"
          x-data="orcamentoDetalhe()"
          x-init="init()">

        {{-- Cabeçalho com totais e configuração --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                <div class="text-center p-3 bg-gray-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">Itens</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="itens.length"></p>
                </div>
                <div class="text-center p-3 bg-blue-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">Custo Total</p>
                    <p class="text-lg font-bold text-blue-700" x-text="formatarMoeda(totalCusto())"></p>
                </div>
                <div class="text-center p-3 bg-green-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">Venda Total</p>
                    <p class="text-lg font-bold text-green-700" x-text="formatarMoeda(totalVenda())"></p>
                </div>
                <div class="text-center p-3 bg-yellow-50 rounded-xl">
                    <p class="text-xs text-gray-500 mb-1">Lucro</p>
                    <p class="text-lg font-bold text-yellow-700" x-text="formatarMoeda(totalVenda() - totalCusto())"></p>
                </div>
            </div>

            {{-- Dados do orçamento + configurações --}}
            <div class="border-t border-gray-100 pt-4 space-y-3">

                {{-- Modo visualização --}}
                <div x-show="!editando" class="flex items-start justify-between gap-3">
                    <div class="space-y-0.5 min-w-0">
                        <p class="text-sm font-semibold text-gray-800" x-text="dados.nome"></p>
                        <p class="text-xs text-gray-500">
                            Cliente: <span class="font-medium text-gray-700" x-text="dados.cliente || '—'"></span>
                        </p>
                        <p class="text-xs text-gray-500">
                            Margem padrão: <span class="font-medium text-gray-700" x-text="dados.margem_padrao + '%'"></span>
                        </p>
                        <p x-show="dados.observacoes" class="text-xs text-gray-500 mt-1" x-text="dados.observacoes"></p>
                    </div>
                    <button @click="abrirEdicao()"
                            class="shrink-0 text-xs text-blue-600 hover:underline border border-blue-200 hover:bg-blue-50 px-3 py-1.5 rounded-lg transition-colors">
                        ✏️ Editar dados
                    </button>
                </div>

                {{-- Modo edição --}}
                <div x-show="editando" x-cloak class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Nome do orçamento *</label>
                        <input type="text" x-model="dados.nome"
                               class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Cliente</label>
                        <input type="text" x-model="dados.cliente" placeholder="Nome do cliente"
                               class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Margem padrão (%)</label>
                        <input type="number" x-model.number="dados.margem_padrao" min="0" max="500" step="0.5"
                               class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-400 mt-1">Aplicada a itens sem margem individual</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Observações</label>
                        <textarea x-model="dados.observacoes" rows="2" placeholder="Observações (opcional)"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 resize-none"></textarea>
                    </div>
                    <div class="sm:col-span-2 flex gap-2">
                        <button @click="salvarDados()" :disabled="salvandoDados"
                                class="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-300 text-white text-xs font-semibold px-4 py-1.5 rounded-lg transition-colors">
                            <span x-show="!salvandoDados">Salvar</span>
                            <span x-show="salvandoDados">Salvando...</span>
                        </button>
                        <button @click="editando = false"
                                class="border border-gray-300 text-gray-600 text-xs px-4 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <p x-show="erroDados" class="text-xs text-red-600 self-center" x-text="erroDados"></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabela de itens --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-700">Itens do Orçamento</h2>
                <a href="{{ route('ferramentas.index') }}"
                   class="text-xs text-blue-600 hover:underline">+ Adicionar mais itens</a>
            </div>

            <template x-if="itens.length === 0">
                <div class="text-center py-12 text-gray-400">
                    <p class="text-sm">Nenhum item ainda.</p>
                    <a href="{{ route('ferramentas.index') }}" class="text-xs text-blue-500 hover:underline mt-1 inline-block">
                        Pesquisar ferramentas
                    </a>
                </div>
            </template>

            <template x-if="itens.length > 0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide border-b border-gray-100">
                                <th class="px-4 py-3 text-left w-14">Img</th>
                                <th class="px-4 py-3 text-left">Produto</th>
                                <th class="px-4 py-3 text-right w-28">Custo Unit.</th>
                                <th class="px-4 py-3 text-center w-20">Qtd</th>
                                <th class="px-4 py-3 text-center w-24">Margem %</th>
                                <th class="px-4 py-3 text-right w-28">Venda Unit.</th>
                                <th class="px-4 py-3 text-right w-28">Total Venda</th>
                                <th class="px-4 py-3 text-center w-16"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, idx) in itens" :key="item.id">
                                <tr :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                                    class="border-b border-gray-100">

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
                                        <p class="text-gray-800 font-medium leading-snug" x-text="item.nome"></p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span x-show="item.site" class="text-xs text-gray-400" x-text="item.site"></span>
                                            <a x-show="item.url" :href="item.url" target="_blank"
                                               class="text-xs text-blue-500 hover:underline">Ver produto ↗</a>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2 text-right text-gray-700 font-medium"
                                        x-text="formatarMoeda(item.preco_custo)"></td>

                                    <td class="px-4 py-2 text-center">
                                        <input type="number"
                                               x-model.number="item.quantidade"
                                               @change="atualizarItem(item)"
                                               min="1"
                                               class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-sm text-center focus:outline-none focus:ring-2 focus:ring-blue-400">
                                    </td>

                                    <td class="px-4 py-2 text-center">
                                        <input type="number"
                                               :placeholder="dados.margem_padrao + '%'"
                                               x-model.number="item.margem"
                                               @change="atualizarItem(item)"
                                               min="0" max="500" step="0.5"
                                               class="w-20 border border-gray-300 rounded-lg px-2 py-1 text-sm text-center focus:outline-none focus:ring-2 focus:ring-green-400">
                                    </td>

                                    <td class="px-4 py-2 text-right text-green-700 font-semibold"
                                        x-text="formatarMoeda(precoVenda(item))"></td>

                                    <td class="px-4 py-2 text-right text-green-800 font-bold"
                                        x-text="formatarMoeda(precoVenda(item) * item.quantidade)"></td>

                                    <td class="px-4 py-2 text-center">
                                        <button @click="removerItem(item)"
                                                class="text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg p-1.5 transition-colors">
                                            &#128465;
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 border-t-2 border-gray-200">
                                <td colspan="5" class="px-4 py-3 text-sm font-semibold text-gray-600 text-right">Totais</td>
                                <td class="px-4 py-3 text-right text-blue-700 font-bold" x-text="formatarMoeda(totalCusto())"></td>
                                <td class="px-4 py-3 text-right text-green-700 font-bold text-base" x-text="formatarMoeda(totalVenda())"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </template>
        </div>

    </main>

    <script>
    function orcamentoDetalhe() {
        return {
            itens:       @json($orcamento->itens),
            orcamentoId: {{ $orcamento->id }},
            editando:    false,
            salvandoDados: false,
            erroDados:   null,
            dados: {
                nome:          @json($orcamento->nome),
                cliente:       @json($orcamento->cliente),
                observacoes:   @json($orcamento->observacoes),
                margem_padrao: {{ (float) $orcamento->margem_padrao }},
            },

            init() {},

            get margemPadrao() { return this.dados.margem_padrao; },

            abrirEdicao() {
                this.erroDados = null;
                this.editando  = true;
            },

            async salvarDados() {
                this.erroDados = null;
                if (!this.dados.nome?.trim()) { this.erroDados = 'Nome obrigatório.'; return; }
                this.salvandoDados = true;
                try {
                    await axios.patch(`/orcamentos/${this.orcamentoId}`, {
                        nome:          this.dados.nome.trim(),
                        cliente:       this.dados.cliente?.trim() || null,
                        observacoes:   this.dados.observacoes?.trim() || null,
                        margem_padrao: this.dados.margem_padrao,
                    });
                    this.editando = false;
                } catch (_) { this.erroDados = 'Erro ao salvar.'; }
                finally { this.salvandoDados = false; }
            },

            formatarMoeda(valor) {
                return 'R$ ' + parseFloat(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            margemEfetiva(item) {
                return item.margem !== null && item.margem !== '' ? parseFloat(item.margem) : this.margemPadrao;
            },

            precoVenda(item) {
                return parseFloat(item.preco_custo) * (1 + this.margemEfetiva(item) / 100);
            },

            totalCusto() {
                return this.itens.reduce((s, i) => s + parseFloat(i.preco_custo) * parseInt(i.quantidade), 0);
            },

            totalVenda() {
                return this.itens.reduce((s, i) => s + this.precoVenda(i) * parseInt(i.quantidade), 0);
            },

            async atualizarItem(item) {
                try {
                    await axios.patch(`/orcamentos/${this.orcamentoId}/itens/${item.id}`, {
                        quantidade: item.quantidade,
                        margem:     item.margem !== '' ? item.margem : null,
                    });
                } catch (_) { alert('Erro ao atualizar item.'); }
            },

            async removerItem(item) {
                if (!confirm(`Remover "${item.nome}" do orçamento?`)) return;
                try {
                    await axios.delete(`/orcamentos/${this.orcamentoId}/itens/${item.id}`);
                    this.itens = this.itens.filter(i => i.id !== item.id);
                } catch (_) { alert('Não foi possível remover.'); }
            },
        };
    }
    </script>
</body>
</html>
