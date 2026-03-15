<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Meus Orçamentos</title>
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
            <a href="{{ route('ferramentas.index') }}"
               class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 transition-colors text-lg shrink-0">
                &#8592;
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Meus Orçamentos</h1>
                <p class="text-xs text-gray-500 hidden sm:block">Gerencie e exporte seus orçamentos</p>
            </div>
        </x-slot:slot>
        <x-slot:actions>
            <button @click.prevent="modalCriarAberto = true"
                    x-data="{ modalCriarAberto: false }"
                    @click="$dispatch('abrir-criar')"
                    class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                + Novo Orçamento
            </button>
        </x-slot:actions>
    </x-app-header>

    <main class="max-w-4xl mx-auto px-4 py-6"
          x-data="orcamentosPage()"
          x-init="init()"
          @abrir-criar.window="abrirCriar()">

        {{-- Modal criar --}}
        <div x-show="modalCriar" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4 fade-in" @click.stop>
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-gray-900">Novo Orçamento</h2>
                    <button @click="modalCriar = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Nome *</label>
                        <input type="text" x-model="form.nome" placeholder="Ex: Orçamento João Silva"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Cliente</label>
                        <input type="text" x-model="form.cliente" placeholder="Nome do cliente (opcional)"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Margem de lucro padrão (%)</label>
                        <input type="number" x-model.number="form.margem_padrao" min="0" max="500" step="0.5"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-400 mt-1">Aplicada a todos os itens sem margem individual</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Observações</label>
                        <textarea x-model="form.observacoes" rows="2" placeholder="Observações (opcional)"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>
                    </div>
                </div>
                <p x-show="erroForm" x-cloak class="text-xs text-red-600" x-text="erroForm"></p>
                <div class="flex gap-2">
                    <button @click="modalCriar = false"
                            class="flex-1 border border-gray-300 text-gray-600 text-sm py-2 rounded-lg hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button @click="criar()" :disabled="salvando"
                            class="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-green-300 text-white text-sm font-semibold py-2 rounded-lg">
                        <span x-show="!salvando">Criar</span>
                        <span x-show="salvando">Salvando...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Lista vazia --}}
        <template x-if="orcamentos.length === 0">
            <div class="text-center py-20 text-gray-400">
                <div class="text-5xl mb-3">&#128203;</div>
                <p class="text-sm">Nenhum orçamento ainda.</p>
                <p class="text-xs mt-1">Adicione itens a partir da tela de busca.</p>
            </div>
        </template>

        {{-- Grid de orçamentos --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <template x-for="orc in orcamentos" :key="orc.id">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex flex-col gap-3 fade-in">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-bold text-gray-900 truncate" x-text="orc.nome"></p>
                            <p class="text-xs text-gray-400 mt-0.5" x-text="orc.cliente ? '👤 ' + orc.cliente : 'Sem cliente'"></p>
                        </div>
                        <span class="shrink-0 bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full border border-blue-200"
                              x-text="orc.itens_count + ' item(ns)'"></span>
                    </div>

                    <div class="flex gap-2 mt-auto">
                        <a :href="'/orcamentos/' + orc.id"
                           class="flex-1 text-center bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2 rounded-lg transition-colors">
                            Abrir
                        </a>
                        <a :href="'/orcamentos/' + orc.id + '/pdf'" target="_blank"
                           class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold py-2 rounded-lg transition-colors">
                            &#128196; PDF
                        </a>
                        <button @click="excluir(orc)"
                                class="text-red-400 hover:text-red-600 px-2 py-2 rounded-lg hover:bg-red-50 transition-colors text-sm">
                            &#128465;
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </main>

    <script>
    function orcamentosPage() {
        return {
            orcamentos: @json($orcamentos),
            modalCriar: false,
            salvando:   false,
            erroForm:   null,
            form: { nome: '', cliente: '', margem_padrao: 0, observacoes: '' },

            init() {},

            abrirCriar() {
                this.form     = { nome: '', cliente: '', margem_padrao: 0, observacoes: '' };
                this.erroForm = null;
                this.modalCriar = true;
            },

            async criar() {
                this.erroForm = null;
                if (!this.form.nome.trim()) { this.erroForm = 'Informe um nome.'; return; }
                this.salvando = true;
                try {
                    const res = await axios.post('/orcamentos', this.form);
                    window.location.href = '/orcamentos/' + res.data.id;
                } catch (e) {
                    this.erroForm = e.response?.data?.message || 'Erro ao criar.';
                    this.salvando = false;
                }
            },

            async excluir(orc) {
                if (!confirm(`Excluir orçamento "${orc.nome}"? Esta ação não pode ser desfeita.`)) return;
                try {
                    await axios.delete('/orcamentos/' + orc.id);
                    this.orcamentos = this.orcamentos.filter(o => o.id !== orc.id);
                } catch (_) { alert('Não foi possível excluir.'); }
            },
        };
    }
    </script>
</body>
</html>
