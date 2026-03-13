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
    ];

    protected $casts = [
        'preco' => 'decimal:2',
        'mais_barato' => 'boolean',
    ];

    public function ferramentaBusca(): BelongsTo
    {
        return $this->belongsTo(FerramentaBusca::class, 'ferramenta_busca_id');
    }

    public function getNomeSiteAttribute(): string
    {
        return match ($this->site) {
            'mercadolivre'   => 'Mercado Livre',
            'lojadomecanico' => 'Loja do Mecânico',
            'anhanguera'     => 'Anhanguera Ferramentas',
            'antferramentas' => 'ANT Ferramentas',
            'kennedy'        => 'Ferramentas Kennedy',
            'lfmaquinas'     => 'LF Máquinas e Ferramentas',
            'martineli'      => 'Martineli Ferramentas',
            'mabore'         => 'Mabore Ferramentas',
            'fermaquinas'    => 'Fermáquinas',
            default          => ucfirst($this->site),
        };
    }
}
