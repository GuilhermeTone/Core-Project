<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buscas específicas</title>
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
            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-600 text-white text-xl shrink-0">&#128269;</div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Buscas específicas</h1>
                <p class="text-xs text-gray-500 hidden sm:block">Pesquise itens fora das lojas cadastradas</p>
            </div>
        </x-slot:slot>
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
                    placeholder="Ex.: Tinta amarelo Maza 3,6L, torneira cozinha, peça específica..."
                    class="flex-1 border border-gray-300 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    autocomplete="off"
                />
                <button
                    type="submit"
                    :disabled="enviando || termo.trim().length < 2"
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

            {{-- ── Marcas trabalhadas ── --}}
            <div class="pt-3 border-t border-gray-100">
                <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Marcas que trabalhamos</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($marcasTrabalhadas as $marca)
                        <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-semibold text-gray-700">
                            {{ $marca }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Estado vazio ── --}}
        <template x-if="buscas.length === 0">
            <div class="text-center py-16 text-gray-400">
                <div class="text-5xl mb-3">&#128269;</div>
                <p class="text-sm">Nenhuma busca ainda. Digite um produto específico para consultar.</p>
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
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </template>

    </main>

    <footer class="text-center text-xs text-gray-400 py-6">
        Buscas específicas &mdash; dados coletados em tempo real
    </footer>

    <script>
    const SITE_NOMES = {
        serper:         'Google Shopping',
    };

    const SITE_BADGE_CLASSES = {
        serper:         'bg-blue-100 text-blue-800 border-blue-300',
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

            init() {
                const iniciais = @json($buscasJson);
                this.buscas = iniciais.map(b => montarBusca(b, null));
                this.polling = setInterval(() => this.pollAtivas(), 2000);
            },

            async buscar() {
                if (this.termo.trim().length < 2) return;
                this.enviando  = true;
                this.erroEnvio = null;
                const termoEnviado = this.termo.trim();

                try {
                    const res = await axios.post('/buscas-especificas/buscar', {
                        termo: termoEnviado,
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
                    const res  = await axios.get(`/buscas-especificas/${busca.id}/status`);
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
                    await axios.delete(`/buscas-especificas/${busca.id}`);
                    this.buscas = this.buscas.filter(b => b.id !== busca.id);
                } catch (_) { alert('Não foi possível excluir.'); }
            },
        };
    }
    </script>
</body>
</html>
