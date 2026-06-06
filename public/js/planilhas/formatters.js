(function () {
    window.PlanilhaCotacao = window.PlanilhaCotacao || {};

    function normalizarTexto(texto) {
        return String(texto || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9+\/,. ]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function medidasNoTexto(texto) {
        const normalizado = normalizarTexto(texto)
            .replace(/\bpol\b/g, 'in')
            .replace(/\bpolegada(s)?\b/g, 'in')
            .replace(/\s+/g, ' ');
        const matches = normalizado.match(/\b\d+(?:[,.]\d+)?(?:\/\d+)?\s?(?:mm|cm|m|in|kg|g|l|ml)\b/g) || [];

        return matches.map(medida => medida.replace(/\s+/g, '').replace(',', '.'));
    }

    function formatarDataCurta(valor) {
        const data = new Date(valor);

        if (Number.isNaN(data.getTime())) {
            return '-';
        }

        return data.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function precoComMargem(preco, margemPercentual) {
        if (preco === null || preco === undefined || preco === '') {
            return null;
        }

        return Number(preco) * (1 + (Number(margemPercentual || 0) / 100));
    }

    function formatarPreco(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return 'sem preço';
        }

        return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    window.PlanilhaCotacao.formatters = {
        normalizarTexto,
        medidasNoTexto,
        formatarDataCurta,
        precoComMargem,
        formatarPreco,
    };
})();
