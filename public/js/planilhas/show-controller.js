(function () {
    const constants = window.PlanilhaCotacao.constants;
    const formatters = window.PlanilhaCotacao.formatters;
    const tags = window.PlanilhaCotacao.tags;

    function configurarAxios() {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (window.axios && token) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    }

    function dadosIniciais() {
        const script = document.getElementById('planilha-inicial');

        if (!script) {
            return {};
        }

        try {
            return JSON.parse(script.textContent || '{}');
        } catch (error) {
            console.error('Não foi possível carregar os dados da planilha.', error);
            return {};
        }
    }

    function planilhaDetalhe(inicial = null) {
        return {
            ...(inicial || dadosIniciais()),
            timer: null,
            revalidando: false,
            revalidacaoResumo: null,
            legendaAberta: false,

            init() {
                if (!['concluido', 'erro'].includes(this.status)) {
                    this.iniciarPolling();
                    this.atualizar();
                }
            },

            iniciarPolling() {
                if (!this.timer) {
                    this.timer = setInterval(() => this.atualizar(), 4000);
                }
            },

            atualizar() {
                axios.get(this.statusUrl).then(({ data }) => {
                    this.status = data.status;
                    this.total = data.total_itens;
                    this.processados = data.itens_processados;
                    this.erro = data.erro_mensagem;
                    this.downloadUrl = data.download_url;
                    this.revalidarUrl = data.revalidar_url || this.revalidarUrl;
                    this.itens = data.itens.map(novo => {
                        const atual = this.itens.find(i => i.id === novo.id);
                        return this.mesclarEstadoItem(novo, atual);
                    });

                    if (['concluido', 'erro'].includes(this.status) && this.timer) {
                        clearInterval(this.timer);
                        this.timer = null;
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
                return {
                    pendente: 'Pendente',
                    processando: 'Processando',
                    concluido: 'Concluida',
                    erro: 'Erro',
                }[this.status] || this.status;
            },

            temSelecionados() {
                return (this.itens || []).some(item => item.resultado_escolhido);
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

            labelRevalidacao(status) {
                return {
                    pendente: 'Revalidação pendente',
                    ok: 'Preço revalidado',
                    preco_alterado: 'Preço atualizado',
                    nao_encontrado: 'Não encontrado agora',
                    erro: 'Erro na revalidação',
                }[status] || 'Sem revalidação';
            },

            revalidacaoClasse(status) {
                return {
                    pendente: 'bg-yellow-50 text-yellow-700 border-yellow-200',
                    ok: 'bg-green-50 text-green-700 border-green-200',
                    preco_alterado: 'bg-blue-50 text-blue-700 border-blue-200',
                    nao_encontrado: 'bg-orange-50 text-orange-700 border-orange-200',
                    erro: 'bg-red-50 text-red-700 border-red-200',
                }[status] || 'bg-gray-100 text-gray-600 border-gray-200';
            },

            textoResumoRevalidacao() {
                if (!this.revalidacaoResumo) return '';

                return `${this.revalidacaoResumo.total} selecionado(s), `
                    + `${this.revalidacaoResumo.ok} ok, `
                    + `${this.revalidacaoResumo.preco_alterado} com preço atualizado, `
                    + `${this.revalidacaoResumo.nao_encontrado} não encontrado(s), `
                    + `${this.revalidacaoResumo.erro} erro(s).`;
            },

            revalidarSelecionados() {
                if (!this.finalizada() || !this.temSelecionados() || this.revalidando) return;

                this.revalidando = true;

                axios.post(this.revalidarUrl).then(({ data }) => {
                    this.downloadUrl = data.download_url;
                    this.revalidacaoResumo = data.resumo;
                    this.atualizarItens(data.itens);
                }).finally(() => {
                    this.revalidando = false;
                });
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

                axios.patch(item.margem_url, {
                    margem_percentual: Number(item.margem_percentual || 0),
                }).then(({ data }) => {
                    this.downloadUrl = data.download_url;
                    this.atualizarItens(data.itens, item.id);
                });
            },

            termoBuscaExibicao(item) {
                return String(item.termo_busca || item.descricao || '').trim();
            },

            editarBusca(item) {
                if (!this.finalizada()) return;

                item.termo_busca_edicao = this.termoBuscaExibicao(item);
                item.editando_busca = true;
            },

            cancelarEdicaoBusca(item) {
                item.termo_busca_edicao = this.termoBuscaExibicao(item);
                item.editando_busca = false;
            },

            termoBuscaEdicaoValido(item) {
                return String(item.termo_busca_edicao || '').trim().length >= 2;
            },

            refazerBusca(item) {
                if (!this.finalizada() || !this.termoBuscaEdicaoValido(item)) return;

                const termoBusca = String(item.termo_busca_edicao || '').trim();

                axios.post(item.refazer_busca_url, {
                    termo_busca: termoBusca,
                }).then(({ data }) => {
                    this.status = data.status;
                    this.total = data.total_itens;
                    this.processados = data.itens_processados;
                    this.erro = data.erro_mensagem;
                    this.downloadUrl = data.download_url;
                    item.termo_busca = termoBusca;
                    item.editando_busca = false;
                    this.atualizarItens(data.itens, item.id);
                    this.iniciarPolling();
                });
            },

            atualizarItens(novosItens, itemAbertoId = null) {
                this.itens = novosItens.map(novo => {
                    const atual = this.itens.find(i => i.id === novo.id);
                    const item = this.mesclarEstadoItem(novo, atual);

                    if (item.id === itemAbertoId) {
                        item.aberto = true;
                    }

                    return item;
                });
            },

            mesclarEstadoItem(novo, atual = null) {
                return {
                    ...novo,
                    aberto: atual ? atual.aberto : false,
                    editando_busca: atual ? atual.editando_busca : false,
                    termo_busca_edicao: atual?.editando_busca
                        ? atual.termo_busca_edicao
                        : String(novo.termo_busca || novo.descricao || '').trim(),
                };
            },

            resultadoSelecionado(item, resultado) {
                return tags.resultadoSelecionado(item, resultado);
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
                return resultado.nome_site || constants.siteNomes[resultado.site] || resultado.site || 'Loja';
            },

            siteBadgeClass(site) {
                return constants.siteBadgeClasses[site] || 'bg-gray-100 text-gray-700 border-gray-300';
            },

            tagsResultado(item, resultado, index) {
                return tags.tagsResultado(item, resultado, index);
            },

            legendaTags() {
                return tags.legendaTags();
            },

            evidenciasResultado(resultado) {
                return tags.evidenciasResultado(resultado);
            },

            precoComMargem(preco, margemPercentual) {
                return formatters.precoComMargem(preco, margemPercentual);
            },

            formatarPreco(valor) {
                return formatters.formatarPreco(valor);
            },

            formatarScore(valor) {
                return formatters.formatarScore(valor);
            }
        };
    }

    configurarAxios();
    window.planilhaDetalhe = planilhaDetalhe;
})();
