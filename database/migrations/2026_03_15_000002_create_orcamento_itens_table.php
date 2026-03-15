<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcamento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orcamento_id')->constrained('orcamentos')->cascadeOnDelete();
            $table->unsignedBigInteger('resultado_busca_id')->nullable();
            $table->string('nome');
            $table->string('site')->nullable();
            $table->decimal('preco_custo', 10, 2);
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->decimal('margem', 5, 2)->nullable();
            $table->text('url')->nullable();
            $table->text('imagem')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamento_itens');
    }
};
