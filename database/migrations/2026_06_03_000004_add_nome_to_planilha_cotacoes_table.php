<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilha_cotacoes', function (Blueprint $table) {
            $table->string('nome')->nullable()->after('user_id');
        });

        $nomesPorUsuario = [];

        DB::table('planilha_cotacoes')
            ->orderBy('id')
            ->get(['id', 'user_id', 'nome_arquivo'])
            ->each(function ($planilha) use (&$nomesPorUsuario): void {
                $base = pathinfo((string) $planilha->nome_arquivo, PATHINFO_FILENAME) ?: "Planilha {$planilha->id}";
                $base = trim(mb_substr($base, 0, 240)) ?: "Planilha {$planilha->id}";
                $nome = $base;
                $contador = 2;
                $chaveUsuario = (string) $planilha->user_id;

                while (isset($nomesPorUsuario[$chaveUsuario][mb_strtolower($nome)])) {
                    $sufixo = " ({$contador})";
                    $nome = mb_substr($base, 0, 255 - mb_strlen($sufixo)).$sufixo;
                    $contador++;
                }

                $nomesPorUsuario[$chaveUsuario][mb_strtolower($nome)] = true;

                DB::table('planilha_cotacoes')
                    ->where('id', $planilha->id)
                    ->update(['nome' => $nome]);
            });

        Schema::table('planilha_cotacoes', function (Blueprint $table) {
            $table->unique(['user_id', 'nome'], 'planilha_cotacoes_user_nome_unique');
        });
    }

    public function down(): void
    {
        Schema::table('planilha_cotacoes', function (Blueprint $table) {
            $table->dropUnique('planilha_cotacoes_user_nome_unique');
            $table->dropColumn('nome');
        });
    }
};
