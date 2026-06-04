<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultadoBusca extends Model
{
    protected $table = 'resultado_buscas';

    protected $fillable = [
        'ferramenta_busca_id',
        'site',
        'nome',
        'descricao',
        'preco',
        'url',
        'imagem',
        'mais_barato',
        'marca_detectada',
        'score_confianca_marca',
        'atributos_extraidos',
        'score_produto',
        'correspondencia_fraca',
    ];

    protected $casts = [
        'preco' => 'decimal:2',
        'mais_barato' => 'boolean',
        'score_confianca_marca' => 'float',
        'atributos_extraidos' => 'array',
        'score_produto' => 'float',
        'correspondencia_fraca' => 'boolean',
    ];

    public function ferramentaBusca(): BelongsTo
    {
        return $this->belongsTo(FerramentaBusca::class, 'ferramenta_busca_id');
    }

    public function getNomeSiteAttribute(): string
    {
        return match ($this->site) {
            'serper' => 'Google Shopping',
            'mercadolivre' => 'Mercado Livre',
            'lojadomecanico' => 'Loja do Mecânico',
            'anhanguera' => 'Anhanguera Ferramentas',
            'antferramentas' => 'ANT Ferramentas',
            'kennedy' => 'Ferramentas Kennedy',
            'lfmaquinas' => 'LF Máquinas e Ferramentas',
            'martineli' => 'Martineli Ferramentas',
            'mabore' => 'Mabore Ferramentas',
            'fermaquinas' => 'Fermáquinas',
            'casadofrentista' => 'Casa do Frentista',
            'agrelimaquinas' => 'Agreli Máquinas',
            'palaciodasferramentas' => 'Palácio das Ferramentas',
            'brenfeer' => 'Brenfeer',
            'tramontinaoficial' => 'Tramontina Loja Oficial',
            'dimensional' => 'Dimensional',
            'gravia' => 'Gravia',
            'arcazul' => 'Arcazul Ferramentas',
            'minasferramentas' => 'Minas Ferramentas',
            default => ucfirst($this->site),
        };
    }
}
