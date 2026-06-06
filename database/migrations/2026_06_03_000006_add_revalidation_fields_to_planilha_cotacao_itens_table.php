<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilha_cotacao_itens', function (Blueprint $table): void {
            $table->decimal('preco_loja', 12, 2)->nullable()->after('valor_unitario');
            $table->decimal('preco_revalidado', 12, 2)->nullable()->after('preco_loja');
            $table->string('revalidacao_status', 40)->nullable()->after('resultado_escolhido');
            $table->timestamp('revalidado_em')->nullable()->after('revalidacao_status');
            $table->text('revalidacao_mensagem')->nullable()->after('revalidado_em');
        });
    }

    public function down(): void
    {
        Schema::table('planilha_cotacao_itens', function (Blueprint $table): void {
            $table->dropColumn([
                'preco_loja',
                'preco_revalidado',
                'revalidacao_status',
                'revalidado_em',
                'revalidacao_mensagem',
            ]);
        });
    }
};
