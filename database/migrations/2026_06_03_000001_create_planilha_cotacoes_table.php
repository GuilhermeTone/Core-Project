<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilha_cotacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nome_arquivo');
            $table->string('arquivo_original');
            $table->string('arquivo_processado')->nullable();
            $table->enum('status', ['pendente', 'processando', 'concluido', 'erro'])->default('pendente');
            $table->unsignedInteger('total_itens')->default(0);
            $table->unsignedInteger('itens_processados')->default(0);
            $table->text('erro_mensagem')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilha_cotacoes');
    }
};
