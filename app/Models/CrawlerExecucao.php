<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlerExecucao extends Model
{
    protected $table = 'crawler_execucoes';

    protected $fillable = [
        'user_id',
        'planilha_cotacao_id',
        'planilha_cotacao_item_id',
        'loja_id',
        'loja_nome',
        'termo',
        'status',
        'resultados_count',
        'duracao_ms',
        'erro_mensagem',
    ];

    protected $casts = [
        'resultados_count' => 'integer',
        'duracao_ms' => 'integer',
    ];

    public function planilha(): BelongsTo
    {
        return $this->belongsTo(PlanilhaCotacao::class, 'planilha_cotacao_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PlanilhaCotacaoItem::class, 'planilha_cotacao_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
