<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultado_buscas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ferramenta_busca_id')->constrained('ferramentas_buscas')->onDelete('cascade');
            $table->string('site');
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('preco', 10, 2)->nullable();
            $table->string('url', 1000);
            $table->string('imagem', 1000)->nullable();
            $table->boolean('mais_barato')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultado_buscas');
    }
};
