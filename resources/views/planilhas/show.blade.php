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
          x-data="planilhaDetalhe()"
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
            <div class="flex flex-col sm:flex-row gap-2">
                <template x-if="finalizada()">
                    <a href="{{ route('planilhas.cotacao-fechada', $planilha) }}"
                       class="inline-flex justify-center text-sm font-bold px-5 py-2.5 rounded-lg border border-amber-500 bg-amber-500 text-white shadow-sm hover:bg-amber-600 hover:border-amber-600 transition-colors">
                        Cotação fechada
                    </a>
                </template>
                <template x-if="finalizada()">
                    <button type="button"
                            @click="revalidarSelecionados()"
                            :disabled="revalidando || !temSelecionados()"
                            :class="revalidando || !temSelecionados()
                                ? 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed'
                                : 'bg-blue-600 hover:bg-blue-700 text-white'"
                            class="inline-flex justify-center text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                        <span x-text="revalidando ? 'Revalidando...' : 'Revalidar selecionados'"></span>
                    </button>
                </template>
                <template x-if="finalizada() && downloadUrl">
                    <a :href="downloadUrl"
                       :class="revalidando ? 'pointer-events-none bg-gray-300 text-gray-500' : 'bg-emerald-600 hover:bg-emerald-700 text-white'"
                       class="inline-flex justify-center text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                        Baixar planilha cotada
                    </a>
                </template>
            </div>
        </section>

        <template x-if="revalidacaoResumo">
            <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 mb-5 text-sm text-gray-700">
                <span class="font-semibold text-gray-900">Revalidação:</span>
                <span x-text="textoResumoRevalidacao()"></span>
            </section>
        </template>

        <section class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 mb-5 text-sm text-amber-900">
            <p class="font-semibold">Confira antes de fechar a compra</p>
            <p class="mt-1 text-xs leading-relaxed">
                Os valores exibidos vêm da última captura/revalidação. Antes de comprar ou confirmar a cotação com o cliente,
                abra o produto no site da loja e confira preço, estoque, frete, prazo e variações do anúncio.
            </p>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-5 overflow-hidden">
            <button type="button"
                    @click="legendaAberta = !legendaAberta"
                    class="w-full px-4 py-3 flex items-center justify-between gap-3 text-left hover:bg-gray-50 transition-colors">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Legenda</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Faixas explicam a ordem dos resultados e tags destacam evidências.</p>
                </div>
                <span class="text-xs font-semibold text-blue-700 whitespace-nowrap"
                      x-text="legendaAberta ? 'Ocultar legenda' : 'Mostrar legenda'"></span>
            </button>

            <div x-show="legendaAberta" x-cloak class="border-y border-gray-100 bg-gray-50 px-4 py-3">
                <div class="mb-3 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-900">
                    <strong>Confiança</strong> indica o quanto o sistema acredita que o resultado corresponde ao item buscado.
                    Dentro das faixas confiáveis, o menor preço vem primeiro. Nas faixas de revisão, a confiança vem antes do preço.
                </div>
                <div class="mb-4">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Faixas de confiança</h3>
                    <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                        <template x-for="faixa in legendaFaixasConfianca()" :key="faixa.titulo">
                            <div class="rounded-md border px-3 py-2 text-xs"
                                 :class="faixa.classe">
                                <div class="font-bold" x-text="faixa.titulo + ' · ' + faixa.intervalo"></div>
                                <div class="mt-0.5 opacity-80" x-text="faixa.ordenacao"></div>
                            </div>
                        </template>
                    </div>
                </div>
                <div>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Tags dos resultados</h3>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                    <template x-for="tag in legendaTags()" :key="tag.label">
                        <div class="inline-flex items-center gap-1.5 min-w-0">
                            <span class="shrink-0 text-[11px] px-2 py-0.5 rounded-full border font-bold"
                                  :class="tag.classe"
                                  x-text="tag.label"></span>
                            <span class="text-xs text-gray-500" x-text="tag.descricao"></span>
                        </div>
                    </template>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <template x-for="item in itens" :key="item.id">
                <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden"
                         :id="'item-' + item.id">
                    <div class="p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-gray-400" x-text="'Linha ' + item.linha"></span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                      :class="badgeClasse(item.status)"
                                      x-text="item.status"></span>
                            </div>
                            <div class="mt-1">
                                <div x-show="!item.editando_busca" class="flex flex-col sm:flex-row sm:items-center gap-2">
                                    <h2 class="font-semibold text-gray-900" x-text="termoBuscaExibicao(item)"></h2>
                                    <button type="button"
                                            @click="editarBusca(item)"
                                            :disabled="!finalizada()"
                                            class="inline-flex w-fit text-xs font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-blue-700 bg-white hover:bg-blue-50 disabled:text-gray-400 disabled:bg-gray-100 disabled:cursor-not-allowed">
                                        Editar
                                    </button>
                                </div>
                                <div x-show="item.editando_busca" x-cloak class="flex flex-col sm:flex-row sm:items-center gap-2">
                                    <input type="text"
                                           x-model="item.termo_busca_edicao"
                                           :placeholder="item.descricao"
                                           class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <button type="button"
                                            @click="refazerBusca(item)"
                                            :disabled="!termoBuscaEdicaoValido(item)"
                                            :class="!termoBuscaEdicaoValido(item)
                                                ? 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed'
                                                : 'bg-blue-600 hover:bg-blue-700 text-white'"
                                            class="text-sm font-semibold px-4 py-2 rounded-lg transition-colors whitespace-nowrap">
                                        Refazer busca
                                    </button>
                                    <button type="button"
                                            @click="cancelarEdicaoBusca(item)"
                                            class="text-sm font-semibold px-4 py-2 rounded-lg border border-gray-200 text-gray-600 bg-white hover:bg-gray-50 transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">
                                    Título original da linha: <span x-text="item.descricao"></span>
                                    <template x-if="item.termo_busca">
                                        <span class="text-blue-600"> · busca ajustada apenas no sistema</span>
                                    </template>
                                </p>
                            </div>
                            <div class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span>Selecionado: <span class="font-semibold" x-text="item.marca_cotada ? item.marca_cotada : 'nenhum'"></span></span>
                                <span>· Preço loja: <span class="font-semibold" x-text="formatarPreco(item.preco_loja)"></span></span>
                                <span>· Valor planilha: <span class="font-semibold" x-text="formatarPreco(item.valor_unitario)"></span></span>
                                <span>· Resultados: <span x-text="(item.resultados || []).length"></span></span>
                                <template x-if="item.resultado_escolhido">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full border font-semibold"
                                          :class="revalidacaoClasse(item.revalidacao_status)"
                                          x-text="labelRevalidacao(item.revalidacao_status)"></span>
                                </template>
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
                                                    :class="[
                                                        resultadoSelecionado(item, resultado)
                                                            ? 'bg-emerald-50 border-l-4 border-emerald-500'
                                                            : (index === 0 ? 'bg-green-50 border-l-4 border-green-500' : (index % 2 === 0 ? 'bg-white' : 'bg-gray-50')),
                                                        resultado.__inicioFaixa ? resultado.__faixaInfo.borda : ''
                                                    ]">
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
                                                        <template x-if="resultado.__inicioFaixa">
                                                            <div class="mb-2 inline-flex max-w-full flex-wrap items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-semibold"
                                                                 :class="resultado.__faixaInfo.classe">
                                                                <span x-text="resultado.__faixaInfo.titulo"></span>
                                                                <span class="opacity-70" x-text="resultado.__faixaInfo.intervalo"></span>
                                                                <span class="opacity-70">·</span>
                                                                <span x-text="resultado.__faixaInfo.ordenacao"></span>
                                                                <span class="opacity-70">·</span>
                                                                <span x-text="'menor da faixa ' + formatarPreco(resultado.__menorPrecoFaixa)"></span>
                                                            </div>
                                                        </template>
                                                        <div class="flex items-start gap-1.5 flex-wrap">
                                                            <span class="text-gray-900 font-medium leading-snug" x-text="resultado.nome"></span>
                                                        </div>
                                                        <div class="mt-1 flex flex-wrap items-center gap-1 text-xs text-gray-500">
                                                            <span class="text-gray-500" x-text="resultado.marca_detectada || 'sem marca'"></span>
                                                            <span class="text-gray-300">·</span>
                                                            <span class="font-bold text-gray-700"
                                                                  x-text="'Confiança ' + formatarScore(scoreResultado(resultado))"></span>
                                                        </div>
                                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                                            <template x-for="tag in tagsResultado(item, resultado, index)" :key="tag.label">
                                                                <span class="text-[11px] px-2 py-0.5 rounded-full border font-bold"
                                                                      :class="tag.classe"
                                                                      x-text="tag.label"></span>
                                                            </template>
                                                        </div>
                                                        <div class="mt-1 flex flex-wrap gap-1">
                                                            <template x-for="evidencia in evidenciasResultado(resultado)" :key="evidencia">
                                                                <span class="text-[11px] px-1.5 py-0.5 rounded border border-gray-200 bg-white text-gray-500"
                                                                      x-text="evidencia"></span>
                                                            </template>
                                                        </div>
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

    <script type="application/json" id="planilha-inicial">@json($planilhaInicial)</script>
    <script src="{{ asset('js/planilhas/constants.js') }}?v={{ filemtime(public_path('js/planilhas/constants.js')) }}"></script>
    <script src="{{ asset('js/planilhas/formatters.js') }}?v={{ filemtime(public_path('js/planilhas/formatters.js')) }}"></script>
    <script src="{{ asset('js/planilhas/tags.js') }}?v={{ filemtime(public_path('js/planilhas/tags.js')) }}"></script>
    <script src="{{ asset('js/planilhas/show-controller.js') }}?v={{ filemtime(public_path('js/planilhas/show-controller.js')) }}"></script>
</body>
</html>
