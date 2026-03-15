<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrcamentoItem extends Model
{
    protected $table = 'orcamento_itens';

    protected $fillable = [
        'orcamento_id',
        'resultado_busca_id',
        'nome',
        'site',
        'preco_custo',
        'quantidade',
        'margem',
        'url',
        'imagem',
    ];

    protected $casts = [
        'preco_custo' => 'decimal:2',
        'margem'      => 'decimal:2',
        'quantidade'  => 'integer',
    ];

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    /** Retorna a margem efetiva: do item ou do orçamento pai */
    public function margemEfetiva(): float
    {
        return (float) ($this->margem ?? $this->orcamento->margem_padrao ?? 0);
    }

    /** Preço de venda unitário com margem aplicada */
    public function precoVenda(): float
    {
        return (float) $this->preco_custo * (1 + $this->margemEfetiva() / 100);
    }
}
