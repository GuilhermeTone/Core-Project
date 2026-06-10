(function () {
    window.PlanilhaCotacao = window.PlanilhaCotacao || {};

    const constants = window.PlanilhaCotacao.constants;
    const formatters = window.PlanilhaCotacao.formatters;

    function resultadoSelecionado(item, resultado) {
        return item.resultado_escolhido
            && item.resultado_escolhido.url === resultado.url
            && item.resultado_escolhido.nome === resultado.nome;
    }

    function tagClasse(tipo) {
        return {
            melhor: 'bg-emerald-600 text-white border-emerald-700',
            selecionado: 'bg-emerald-100 text-emerald-800 border-emerald-300',
            preco: 'bg-green-100 text-green-800 border-green-300',
            confianca: 'bg-teal-100 text-teal-800 border-teal-300',
            boaConfianca: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            conferir: 'bg-amber-100 text-amber-800 border-amber-300',
            marca: 'bg-blue-100 text-blue-800 border-blue-300',
            medida: 'bg-cyan-100 text-cyan-800 border-cyan-300',
            codigo: 'bg-indigo-100 text-indigo-800 border-indigo-300',
            precoConfirmado: 'bg-lime-100 text-lime-800 border-lime-300',
            precoAtualizado: 'bg-sky-100 text-sky-800 border-sky-300',
            problema: 'bg-red-100 text-red-800 border-red-300',
        }[tipo] || 'bg-gray-100 text-gray-700 border-gray-300';
    }

    function tag(label, tipo) {
        return { label, classe: tagClasse(tipo) };
    }

    function legendaTags() {
        return [
            { label: 'Melhor opção', tipo: 'melhor', descricao: 'Menor preço + alta confiança.' },
            { label: 'Menor preço', tipo: 'preco', descricao: 'Mais barato com preço válido.' },
            { label: 'Alta confiança', tipo: 'confianca', descricao: 'Bate bem com a busca.' },
            { label: 'Boa confiança', tipo: 'boaConfianca', descricao: 'Passou no corte mínimo.' },
            { label: 'Conferir', tipo: 'conferir', descricao: 'Precisa revisão.' },
            { label: 'Marca correta', tipo: 'marca', descricao: 'Marca bate com a busca.' },
            { label: 'Medida correta', tipo: 'medida', descricao: 'Medida bate com a planilha.' },
            { label: 'Código encontrado', tipo: 'codigo', descricao: 'Código buscado apareceu.' },
            { label: 'Preço confirmado', tipo: 'precoConfirmado', descricao: 'Preço revalidado.' },
            { label: 'Preço atualizado', tipo: 'precoAtualizado', descricao: 'Mudou ao revalidar.' },
            { label: 'Sem preço confiável', tipo: 'problema', descricao: 'Não usar para cotação.' },
            { label: 'Selecionado', tipo: 'selecionado', descricao: 'Vai para a planilha.' },
        ].map(item => ({ ...item, classe: tagClasse(item.tipo) }));
    }

    function tagsResultado(item, resultado, index) {
        const tags = [];
        const score = Number(resultado.score_produto || 0);
        const temPreco = !(resultado.preco === null || resultado.preco === undefined || resultado.preco === '');
        const menorPreco = index === 0 && temPreco;
        const selecionado = resultadoSelecionado(item, resultado);
        const altaConfianca = score >= 0.85;
        const boaConfianca = score >= 0.80;
        const compativel = score >= 0.65;
        const revisar = score > 0 && score < 0.80;

        if (selecionado) {
            tags.push(tag('Selecionado', 'selecionado'));
        }

        if (menorPreco && altaConfianca) {
            tags.push(tag('Melhor opção', 'melhor'));
        } else if (menorPreco) {
            tags.push(tag('Menor preço', 'preco'));
        }

        if (altaConfianca) {
            tags.push(tag('Alta confiança', 'confianca'));
        } else if (boaConfianca) {
            tags.push(tag('Boa confiança', 'boaConfianca'));
        } else if (revisar || !compativel) {
            tags.push(tag('Conferir', 'conferir'));
        }

        if (marcaCorreta(item, resultado)) {
            tags.push(tag('Marca correta', 'marca'));
        }

        if (medidaCorreta(item, resultado)) {
            tags.push(tag('Medida correta', 'medida'));
        } else if (buscaTemMedida(item) && !resultadoTemMedida(resultado)) {
            tags.push(tag('Medida não confirmada', 'conferir'));
        }

        if (codigoBuscadoEncontrado(item, resultado)) {
            tags.push(tag('Código encontrado', 'codigo'));
        }

        if (selecionado && item.revalidacao_status === 'ok') {
            tags.push(tag('Preço confirmado', 'precoConfirmado'));
        }

        if (selecionado && item.revalidacao_status === 'preco_alterado') {
            tags.push(tag('Preço atualizado', 'precoAtualizado'));
        }

        if (!temPreco) {
            tags.push(tag('Sem preço confiável', 'problema'));
        }

        return tagsUnicas(tags).slice(0, 7);
    }

    function tagsUnicas(tags) {
        const vistos = new Set();

        return tags.filter(tag => {
            if (vistos.has(tag.label)) return false;
            vistos.add(tag.label);
            return true;
        });
    }

    function marcaCorreta(item, resultado) {
        const marcasBusca = marcasNoTexto(item.descricao || '');
        const marcaResultado = formatters.normalizarTexto(resultado.marca_detectada || '');

        if (!marcaResultado || marcasBusca.length === 0) {
            return false;
        }

        return marcasBusca.some(marca => marca === marcaResultado);
    }

    function marcasNoTexto(texto) {
        const normalizado = ` ${formatters.normalizarTexto(texto)} `;

        return constants.marcas
            .map(marca => formatters.normalizarTexto(marca))
            .filter(marca => normalizado.includes(` ${marca} `));
    }

    function medidaCorreta(item, resultado) {
        const medidasBusca = formatters.medidasNoTexto(item.descricao || '');
        const medidasResultado = medidasDoResultado(resultado);

        if (medidasBusca.length === 0 || medidasResultado.length === 0) {
            return false;
        }

        return medidasBusca.some(medidaBusca => medidasResultado.includes(medidaBusca));
    }

    function buscaTemMedida(item) {
        return formatters.medidasNoTexto(item.descricao || '').length > 0;
    }

    function resultadoTemMedida(resultado) {
        return medidasDoResultado(resultado).length > 0;
    }

    function medidasDoResultado(resultado) {
        const atributos = resultado.atributos_extraidos || {};
        const texto = `${resultado.nome || ''} ${atributos.medida || ''} ${atributos.peso || ''}`;

        return formatters.medidasNoTexto(texto);
    }

    function codigoBuscadoEncontrado(item, resultado) {
        const codigosBusca = Array.isArray(item.codigos_busca)
            ? item.codigos_busca.map(normalizarCodigo).filter(Boolean)
            : [];

        if (codigosBusca.length === 0) {
            return false;
        }

        const atributos = resultado.atributos_extraidos || {};
        const textoResultado = [
            resultado.nome || '',
            resultado.codigo || '',
            atributos.modelo || '',
        ].join(' ');
        const textoNormalizado = normalizarCodigo(textoResultado);

        return codigosBusca.some(codigo => textoNormalizado.includes(codigo));
    }

    function normalizarCodigo(texto) {
        return formatters.normalizarTexto(texto || '').replace(/[^a-z0-9]/g, '');
    }

    function evidenciasResultado(resultado) {
        const atributos = resultado.atributos_extraidos || {};
        const evidencias = [];

        if (resultado.codigo) evidencias.push(`cod. ${resultado.codigo}`);
        if (atributos.tipo) evidencias.push(atributos.tipo);
        if (atributos.medida) evidencias.push(atributos.medida);
        if (atributos.modelo) evidencias.push(atributos.modelo);
        if (atributos.peso) evidencias.push(atributos.peso);
        if (atributos.voltagem) evidencias.push(atributos.voltagem);
        if (resultado.capturado_em) evidencias.push(`capturado ${formatters.formatarDataCurta(resultado.capturado_em)}`);

        return evidencias.filter(Boolean).slice(0, 5);
    }

    window.PlanilhaCotacao.tags = {
        resultadoSelecionado,
        tagClasse,
        legendaTags,
        tagsResultado,
        evidenciasResultado,
    };
})();
