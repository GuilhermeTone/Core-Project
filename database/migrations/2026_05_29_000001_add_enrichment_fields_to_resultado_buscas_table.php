<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resultado_buscas', function (Blueprint $table) {
            $table->string('marca_detectada')->nullable()->after('mais_barato');
            $table->decimal('score_confianca_marca', 4, 2)->nullable()->after('marca_detectada');
            $table->json('atributos_extraidos')->nullable()->after('score_confianca_marca');
            $table->decimal('score_produto', 4, 2)->nullable()->after('atributos_extraidos');
            $table->boolean('correspondencia_fraca')->default(false)->after('score_produto');
        });
    }

    public function down(): void
    {
        Schema::table('resultado_buscas', function (Blueprint $table) {
            $table->dropColumn([
                'marca_detectada',
                'score_confianca_marca',
                'atributos_extraidos',
                'score_produto',
                'correspondencia_fraca',
            ]);
        });
    }
};
