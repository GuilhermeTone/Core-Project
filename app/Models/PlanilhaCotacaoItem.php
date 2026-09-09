<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanilhaCotacaoItem extends Model
{
    protected $table = 'planilha_cotacao_itens';

    protected $fillable = [
        'planilha_cotacao_id',
        'linha',
        'item',
        'sequencia',
        'descricao',
        'termo_busca',
        'unidade',
        'quantidade',
        'marca_cotada',
        'valor_unitario',
        'preco_loja',
        'preco_revalidado',
        'margem_percentual',
        'cod_forn',
        'entrega',
        'desconto',
        'status',
        'lojas_total',
        'lojas_processadas',
        'resultado_escolhido',
        'revalidacao_status',
        'revalidado_em',
        'revalidacao_mensagem',
        'resultados',
        'erro_mensagem',
    ];

    protected $casts = [
        'linha' => 'integer',
        'quantidade' => 'decimal:3',
        'valor_unitario' => 'decimal:2',
        'preco_loja' => 'decimal:2',
        'preco_revalidado' => 'decimal:2',
        'margem_percentual' => 'decimal:2',
        'desconto' => 'decimal:2',
        'lojas_total' => 'integer',
        'lojas_processadas' => 'integer',
        'resultado_escolhido' => 'array',
        'revalidado_em' => 'datetime',
        'resultados' => 'array',
    ];

    public function planilha(): BelongsTo
    {
        return $this->belongsTo(PlanilhaCotacao::class, 'planilha_cotacao_id');
    }
}
