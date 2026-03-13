<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FerramentaBusca extends Model
{
    protected $table = 'ferramentas_buscas';

    protected $fillable = [
        'termo',
        'lojas',
        'status',
        'total_sites',
        'erro_mensagem',
    ];

    protected $casts = [
        'lojas' => 'array',
    ];

    public function resultados(): HasMany
    {
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
