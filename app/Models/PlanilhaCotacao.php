<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanilhaCotacao extends Model
{
    protected $table = 'planilha_cotacoes';

    protected $fillable = [
        'user_id',
        'nome',
        'nome_arquivo',
        'arquivo_original',
        'arquivo_processado',
        'status',
        'total_itens',
        'itens_processados',
        'erro_mensagem',
    ];

    protected $casts = [
        'total_itens' => 'integer',
        'itens_processados' => 'integer',
    ];

    public function itens(): HasMany
    {
        return $this->hasMany(PlanilhaCotacaoItem::class)->orderBy('linha');
    }
}
