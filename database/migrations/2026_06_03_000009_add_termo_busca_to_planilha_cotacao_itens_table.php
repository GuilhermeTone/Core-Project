<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilha_cotacao_itens', function (Blueprint $table): void {
            $table->text('termo_busca')->nullable()->after('descricao');
        });
    }

    public function down(): void
    {
        Schema::table('planilha_cotacao_itens', function (Blueprint $table): void {
            $table->dropColumn('termo_busca');
        });
    }
};
