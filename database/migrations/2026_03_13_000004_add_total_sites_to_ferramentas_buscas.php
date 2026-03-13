<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ferramentas_buscas', function (Blueprint $table) {
            $table->tinyInteger('total_sites')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ferramentas_buscas', function (Blueprint $table) {
            $table->dropColumn('total_sites');
        });
    }
};
