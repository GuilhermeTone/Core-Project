<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ferramentas_buscas', function (Blueprint $table) {
            $table->id();
            $table->string('termo');
            $table->enum('status', ['pendente', 'processando', 'concluido', 'erro'])->default('pendente');
            $table->text('erro_mensagem')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ferramentas_buscas');
    }
};
