<?php

use Illuminate\Support\Facades\Route;
use App\Jobs\TestQueueJob;
use App\Jobs\GenerateUsersReport;
use App\Http\Controllers\FerramentaController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-queue', function () {
    TestQueueJob::dispatch();
    return "Job enviado para fila!";
});

Route::get('/report', function () {

    for ($i = 0; $i < 5; $i++) {
        GenerateUsersReport::dispatch();
    }

    return "Relatório sendo gerado...";
});

Route::prefix('ferramentas')->name('ferramentas.')->group(function () {
    Route::get('/', [FerramentaController::class, 'index'])->name('index');
    Route::post('/buscar', [FerramentaController::class, 'buscar'])->name('buscar');
    Route::get('/{id}/status', [FerramentaController::class, 'status'])->name('status');
    Route::delete('/{id}', [FerramentaController::class, 'destroy'])->name('destroy');
});