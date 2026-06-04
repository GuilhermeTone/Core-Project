<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilha_cotacao_itens', function (Blueprint $table) {
            $table->unsignedInteger('lojas_total')->default(0)->after('status');
            $table->unsignedInteger('lojas_processadas')->default(0)->after('lojas_total');
        });
    }

    public function down(): void
    {
        Schema::table('planilha_cotacao_itens', function (Blueprint $table) {
            $table->dropColumn(['lojas_total', 'lojas_processadas']);
        });
    }
};
