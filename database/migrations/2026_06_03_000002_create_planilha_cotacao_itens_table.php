<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilha_cotacao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilha_cotacao_id')->constrained('planilha_cotacoes')->onDelete('cascade');
            $table->unsignedInteger('linha');
            $table->string('item')->nullable();
            $table->string('sequencia')->nullable();
            $table->text('descricao');
            $table->string('unidade')->nullable();
            $table->decimal('quantidade', 12, 3)->nullable();
            $table->string('marca_cotada')->nullable();
            $table->decimal('valor_unitario', 12, 2)->nullable();
            $table->string('cod_forn')->nullable();
            $table->string('entrega')->nullable();
            $table->decimal('desconto', 12, 2)->nullable();
            $table->enum('status', ['pendente', 'processando', 'concluido', 'sem_resultado', 'erro'])->default('pendente');
            $table->json('resultado_escolhido')->nullable();
            $table->json('resultados')->nullable();
            $table->text('erro_mensagem')->nullable();
            $table->timestamps();

            $table->index(['planilha_cotacao_id', 'linha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilha_cotacao_itens');
    }
};
