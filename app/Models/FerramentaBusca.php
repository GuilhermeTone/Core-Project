<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FerramentaBusca extends Model
{
    protected $table = 'ferramentas_buscas';

    protected $fillable = [
        'user_id',
        'termo',
        'lojas',
        'status',
        'total_sites',
        'erro_mensagem',
    ];

    protected $casts = [
        'lojas' => 'array',
    ];

    /** @phpstan-return HasMany<ResultadoBusca, FerramentaBusca> */
    public function resultados(): HasMany
    {
        /** @phpstan-ignore return.type */
        return $this->hasMany(ResultadoBusca::class, 'ferramenta_busca_id');
    }

    public function scopePendente($query)
    {
        return $query->where('status', 'pendente');
    }

    public function scopeConcluido($query)
    {
        return $query->where('status', 'concluido');
    }
}
