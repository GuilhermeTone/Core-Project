<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilha_cotacao_itens', function (Blueprint $table) {
            $table->decimal('margem_percentual', 8, 2)->default(0)->after('valor_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('planilha_cotacao_itens', function (Blueprint $table) {
            $table->dropColumn('margem_percentual');
        });
    }
};
