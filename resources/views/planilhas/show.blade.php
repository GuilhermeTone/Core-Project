<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $planilha->nome ?? $planilha->nome_arquivo }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <x-app-header>
        <x-slot:slot>
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-600 text-white text-xl shrink-0">&#128196;</div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-gray-900 truncate">{{ $planilha->nome ?? $planilha->nome_arquivo }}</h1>
                <p class="text-xs text-gray-500 hidden sm:block">{{ $planilha->nome_arquivo }}</p>
            </div>
        </x-slot:slot>
        <x-slot:actions>
            <a href="{{ route('planilhas.index') }}"
               class="flex items-center gap-2 bg-gray-700 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                Voltar
            </a>
        </x-slot:actions>
    </x-app-header>

    <main class="max-w-6xl mx-auto px-4 py-6"
          x-data="planilhaDetalhe(window.planilhaInicial)"
          x-init="init()">

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full"
                          :class="status === 'concluido' ? 'bg-green-500' : (status === 'erro' ? 'bg-red-500' : 'bg-yellow-400')"></span>
                    <span class="text-sm font-semibold text-gray-900" x-text="labelStatus()"></span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    <span x-text="processados"></span>/<span x-text="total"></span> item(ns) processado(s)
                </p>
                <div class="w-72 max-w-full h-2 bg-gray-100 rounded-full mt-3 overflow-hidden">
                    <div class="h-full bg-emerald-500 transition-all" :style="`width: ${progresso()}%`"></div>
                </div>
            </div>
            <template x-if="finalizada() && downloadUrl">
                <a :href="downloadUrl"
                   class="inline-flex justify-center text-sm font-semibold px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition-colors">
                    Baixar planilha cotada
                </a>
            </template>
        </section>

        <section class="space-y-3">
            <template x-for="item in itens" :key="item.id">
                <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-gray-400" x-text="'Linha ' + item.linha"></span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                      :class="badgeClasse(item.status)"
                                      x-text="item.status"></span>
                            </div>
                            <h2 class="font-semibold text-gray-900 mt-1" x-text="item.descricao"></h2>
                            <div class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span>Selecionado: <span class="font-semibold" x-text="item.marca_cotada ? item.marca_cotada : 'nenhum'"></span></span>
                                <span>· Valor unit.: <span class="font-semibold" x-text="formatarPreco(item.valor_unitario)"></span></span>
                                <span>· Resultados: <span x-text="(item.resultados || []).length"></span></span>
                                <template x-if="item.status === 'processando' && item.lojas_total">
                                    <span>· Lojas: <span x-text="item.lojas_processadas + '/' + item.lojas_total"></span></span>
                                </template>
                                <label class="inline-flex items-center gap-1.5 ml-0 sm:ml-2">
                                    <span class="font-semibold text-gray-600">Margem</span>
                                    <input type="number"
                                           min="0"
                                           max="999.99"
                                           step="0.01"
                                           x-model.number="item.margem_percentual"
                                           @change="atualizarMargem(item)"
                                           :disabled="!finalizada()"
                                           class="w-20 border border-gray-300 rounded-md px-2 py-1 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <span>%</span>
                                </label>
                            </div>
                        </div>
                        <button type="button"
                                @click="item.aberto = !item.aberto"
                                class="text-sm font-semibold px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                            <span x-text="item.aberto ? 'Fechar resultados' : 'Ver resultados'"></span>
                        </button>
                    </div>

                    <div x-show="item.aberto" x-cloak class="border-t border-gray-100 bg-gray-50">
                        <template x-if="!item.resultados || item.resultados.length === 0">
                            <p class="p-5 text-sm text-gray-500">Nenhum resultado encontrado para este item.</p>
                        </template>

                        <template x-if="item.resultados && item.resultados.length > 0">
                            <div>
                                <div class="px-5 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between gap-3">
                                    <p class="text-xs text-gray-500">
                                        Escolha <span class="font-semibold text-gray-700">um</span> resultado para preencher esta linha da planilha.
                                    </p>
                                    <template x-if="item.resultado_escolhido">
                                        <button type="button"
                                                @click="limpar(item)"
                                                :disabled="!finalizada()"
                                                class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-red-200 bg-white text-red-600 hover:bg-red-50 transition-colors">
                                            Remover seleção
                                        </button>
                                    </template>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
                                                <th class="px-4 py-2 text-left w-14">Img</th>
                                                <th class="px-4 py-2 text-left min-w-[320px]">Produto</th>
                                                <th class="px-4 py-2 text-left w-36">Loja</th>
                                                <th class="px-4 py-2 text-right w-28">Preço</th>
                                                <th class="px-4 py-2 text-center w-24">Link</th>
                                                <th class="px-4 py-2 text-center w-32">Planilha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(resultado, index) in resultadosOrdenados(item)" :key="resultadoKey(resultado)">
                                                <tr class="border-b border-gray-100 hover:bg-yellow-50 transition-colors"
                                                    :class="resultadoSelecionado(item, resultado)
                                                        ? 'bg-emerald-50 border-l-4 border-emerald-500'
                                                        : (index === 0 ? 'bg-green-50 border-l-4 border-green-500' : (index % 2 === 0 ? 'bg-white' : 'bg-gray-50'))">
                                                    <td class="px-4 py-2">
                                                        <template x-if="resultado.imagem">
                                                            <img :src="resultado.imagem" :alt="resultado.nome"
                                                                 class="w-10 h-10 object-contain rounded border border-gray-200 bg-white"
                                                                 onerror="this.style.display='none'">
                                                        </template>
                                                        <template x-if="!resultado.imagem">
                                                            <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center text-gray-400 text-xs">N/A</div>
                                                        </template>
                                                    </td>

                                                    <td class="px-4 py-2">
                                                        <div class="flex items-start gap-1.5 flex-wrap">
                                                            <template x-if="resultadoSelecionado(item, resultado)">
                                                                <span class="shrink-0 bg-emerald-100 text-emerald-800 text-xs font-bold px-1.5 py-0.5 rounded-full border border-emerald-300">SELECIONADO</span>
                                                            </template>
                                                            <template x-if="!resultadoSelecionado(item, resultado) && index === 0">
                                                                <span class="shrink-0 bg-green-100 text-green-800 text-xs font-bold px-1.5 py-0.5 rounded-full border border-green-300">MAIS BARATO</span>
                                                            </template>
                                                            <span class="text-gray-900 font-medium leading-snug" x-text="resultado.nome"></span>
                                                        </div>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            <span x-text="resultado.marca_detectada || 'sem marca'"></span>
                                                            · score <span x-text="resultado.score_produto ?? '-'"></span>
                                                        </p>
                                                    </td>

                                                    <td class="px-4 py-2">
                                                        <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full border"
                                                              :class="siteBadgeClass(resultado.site)"
                                                              x-text="siteNome(resultado)"></span>
                                                    </td>

                                                    <td class="px-4 py-2 text-right">
                                                        <span :class="resultadoSelecionado(item, resultado) || index === 0 ? 'text-emerald-700 font-bold text-base' : 'text-gray-800 font-semibold'"
                                                              x-text="formatarPreco(resultado.preco)"></span>
                                                        <p class="text-[11px] text-gray-400 mt-0.5"
                                                           x-text="'Planilha: ' + formatarPreco(precoComMargem(resultado.preco, item.margem_percentual))"></p>
                                                    </td>

                                                    <td class="px-4 py-2 text-center">
                                                        <a :href="resultado.url" target="_blank" rel="noopener"
                                                           class="inline-block bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
                                                            Abrir
                                                        </a>
                                                    </td>

                                                    <td class="px-4 py-2 text-center">
                                                        <button type="button"
                                                                @click="selecionar(item, resultado.__index)"
                                                                :disabled="!finalizada() || resultadoSelecionado(item, resultado)"
                                                                :class="!finalizada()
                                                                    ? 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed'
                                                                    : (resultadoSelecionado(item, resultado)
                                                                    ? 'bg-emerald-100 text-emerald-700 border border-emerald-300 cursor-default'
                                                                    : 'bg-emerald-600 hover:bg-emerald-700 text-white')"
                                                                class="inline-flex justify-center min-w-[6rem] text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors whitespace-nowrap">
                                                            <span x-text="!finalizada() ? 'Aguarde' : (resultadoSelecionado(item, resultado) ? 'Selecionado' : 'Usar')"></span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </template>
                    </div>
                </article>
            </template>
        </section>
    </main>

    <script>
        const PLANILHA_SITE_NOMES = {
            mercadolivre: 'Mercado Livre',
            lojadomecanico: 'Loja do Mecânico',
            anhanguera: 'Anhanguera Ferramentas',
            antferramentas: 'ANT Ferramentas',
            kennedy: 'Ferramentas Kennedy',
            lfmaquinas: 'LF Máquinas',
            martineli: 'Martineli Ferramentas',
            mabore: 'Mabore Ferramentas',
            fermaquinas: 'Fermáquinas',
            casadofrentista: 'Casa do Frentista',
            agrelimaquinas: 'Agreli Máquinas',
            palaciodasferramentas: 'Palácio das Ferramentas',
            brenfeer: 'Brenfeer',
            tramontinaoficial: 'Tramontina Loja Oficial',
            dimensional: 'Dimensional',
            gravia: 'Gravia',
            arcazul: 'Arcazul Ferramentas',
            minasferramentas: 'Minas Ferramentas',
        };

        const PLANILHA_SITE_BADGE_CLASSES = {
            mercadolivre: 'bg-yellow-100 text-yellow-800 border-yellow-300',
            lojadomecanico: 'bg-blue-100 text-blue-800 border-blue-300',
            anhanguera: 'bg-orange-100 text-orange-800 border-orange-300',
            antferramentas: 'bg-red-100 text-red-800 border-red-300',
            kennedy: 'bg-purple-100 text-purple-800 border-purple-300',
            lfmaquinas: 'bg-teal-100 text-teal-800 border-teal-300',
            martineli: 'bg-green-100 text-green-800 border-green-300',
            mabore: 'bg-pink-100 text-pink-800 border-pink-300',
            fermaquinas: 'bg-indigo-100 text-indigo-800 border-indigo-300',
            casadofrentista: 'bg-cyan-100 text-cyan-800 border-cyan-300',
            agrelimaquinas: 'bg-lime-100 text-lime-800 border-lime-300',
            palaciodasferramentas: 'bg-amber-100 text-amber-800 border-amber-300',
            brenfeer: 'bg-rose-100 text-rose-800 border-rose-300',
            tramontinaoficial: 'bg-sky-100 text-sky-800 border-sky-300',
            dimensional: 'bg-slate-100 text-slate-800 border-slate-300',
            gravia: 'bg-fuchsia-100 text-fuchsia-800 border-fuchsia-300',
            arcazul: 'bg-blue-100 text-blue-800 border-blue-300',
            minasferramentas: 'bg-emerald-100 text-emerald-800 border-emerald-300',
        };

        window.planilhaInicial = @json($planilhaInicial);
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function planilhaDetalhe(inicial) {
            return {
                ...inicial,
                timer: null,
                init() {
                    if (!['concluido', 'erro'].includes(this.status)) {
                        this.timer = setInterval(() => this.atualizar(), 4000);
                        this.atualizar();
                    }
                },
                atualizar() {
                    axios.get(this.statusUrl).then(({ data }) => {
                        this.status = data.status;
                        this.total = data.total_itens;
                        this.processados = data.itens_processados;
                        this.erro = data.erro_mensagem;
                        this.downloadUrl = data.download_url;
                        this.itens = data.itens.map(novo => {
                            const atual = this.itens.find(i => i.id === novo.id);
                            return { ...novo, aberto: atual ? atual.aberto : false };
                        });

                        if (['concluido', 'erro'].includes(this.status) && this.timer) {
                            clearInterval(this.timer);
                        }
                    });
                },
                progresso() {
                    return this.total > 0 ? Math.round((this.processados / this.total) * 100) : 0;
                },
                finalizada() {
                    return this.status === 'concluido';
                },
                labelStatus() {
                    const labels = {
                        pendente: 'Pendente',
                        processando: 'Processando',
                        concluido: 'Concluida',
                        erro: 'Erro',
                    };
                    return labels[this.status] || this.status;
                },
                badgeClasse(status) {
                    return {
                        pendente: 'bg-yellow-50 text-yellow-700 border border-yellow-200',
                        processando: 'bg-blue-50 text-blue-700 border border-blue-200',
                        concluido: 'bg-green-50 text-green-700 border border-green-200',
                        sem_resultado: 'bg-gray-100 text-gray-600 border border-gray-200',
                        erro: 'bg-red-50 text-red-700 border border-red-200',
                    }[status] || 'bg-gray-100 text-gray-600 border border-gray-200';
                },
                selecionar(item, index) {
                    if (!this.finalizada()) return;

                    axios.post(item.selecionar_url, { resultado_index: index }).then(({ data }) => {
                        this.downloadUrl = data.download_url;
                        this.atualizarItens(data.itens, item.id);
                    });
                },
                limpar(item) {
                    if (!this.finalizada()) return;

                    axios.delete(item.limpar_url).then(({ data }) => {
                        this.downloadUrl = data.download_url;
                        this.atualizarItens(data.itens, item.id);
                    });
                },
                atualizarMargem(item) {
                    if (!this.finalizada()) return;

                    const margem = Number(item.margem_percentual || 0);

                    axios.patch(item.margem_url, { margem_percentual: margem }).then(({ data }) => {
                        this.downloadUrl = data.download_url;
                        this.atualizarItens(data.itens, item.id);
                    });
                },
                atualizarItens(novosItens, itemAbertoId = null) {
                    this.itens = novosItens.map(novo => {
                        const atual = this.itens.find(i => i.id === novo.id);
                        return { ...novo, aberto: atual ? atual.aberto || atual.id === itemAbertoId : false };
                    });
                },
                resultadoSelecionado(item, resultado) {
                    return item.resultado_escolhido
                        && item.resultado_escolhido.url === resultado.url
                        && item.resultado_escolhido.nome === resultado.nome;
                },
                resultadosOrdenados(item) {
                    return (item.resultados || [])
                        .map((resultado, index) => ({ ...resultado, __index: index }))
                        .sort((a, b) => {
                            const precoA = a.preco === null || a.preco === undefined ? Infinity : Number(a.preco);
                            const precoB = b.preco === null || b.preco === undefined ? Infinity : Number(b.preco);
                            return precoA - precoB;
                        });
                },
                resultadoKey(resultado) {
                    return `${resultado.__index}-${resultado.url || ''}-${resultado.nome || ''}`;
                },
                siteNome(resultado) {
                    return resultado.nome_site || PLANILHA_SITE_NOMES[resultado.site] || resultado.site || 'Loja';
                },
                siteBadgeClass(site) {
                    return PLANILHA_SITE_BADGE_CLASSES[site] || 'bg-gray-100 text-gray-700 border-gray-300';
                },
                precoComMargem(preco, margemPercentual) {
                    if (preco === null || preco === undefined || preco === '') return null;
                    return Number(preco) * (1 + (Number(margemPercentual || 0) / 100));
                },
                formatarPreco(valor) {
                    if (valor === null || valor === undefined || valor === '') return 'sem preço';
                    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                }
            }
        }
    </script>
</body>
</html>
