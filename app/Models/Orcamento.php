<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orcamento extends Model
{
    protected $fillable = [
        'user_id',
        'nome',
        'cliente',
        'observacoes',
        'margem_padrao',
    ];

    protected $casts = [
        'margem_padrao' => 'decimal:2',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class);
    }

    public function totalCusto(): float
    {
        return (float) $this->itens->sum(fn ($i) => $i->preco_custo * $i->quantidade);
    }

    public function totalVenda(): float
    {
        return (float) $this->itens->sum(fn ($i) => $i->precoVenda() * $i->quantidade);
    }
}
