<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resultado_buscas', function (Blueprint $table) {
            $table->text('url')->change();
            $table->text('imagem')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('resultado_buscas', function (Blueprint $table) {
            $table->string('url', 1000)->change();
            $table->string('imagem', 1000)->nullable()->change();
        });
    }
};
