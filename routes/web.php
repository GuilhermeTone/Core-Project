<?php

use App\Http\Controllers\AssinaturaController;
use App\Http\Controllers\FerramentaController;
use App\Http\Controllers\OrcamentoController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('ferramentas.index');
});

// Rotas do Breeze (perfil)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Assinatura (autenticado, mas sem exigir assinatura ativa)
Route::middleware('auth')->prefix('assinatura')->name('assinatura.')->group(function () {
    Route::get('/', [AssinaturaController::class, 'index'])->name('index');
    Route::post('/checkout', [AssinaturaController::class, 'checkout'])->name('checkout');
    Route::get('/sucesso', [AssinaturaController::class, 'sucesso'])->name('sucesso');
    Route::post('/portal', [AssinaturaController::class, 'portal'])->name('portal');
});

// Rotas da aplicação (requerem autenticação + assinatura ativa)
Route::middleware(['auth', 'subscribed'])->group(function () {
    Route::prefix('ferramentas')->name('ferramentas.')->group(function () {
        Route::get('/', [FerramentaController::class, 'index'])->name('index');
        Route::post('/buscar', [FerramentaController::class, 'buscar'])->name('buscar');
        Route::get('/{id}/status', [FerramentaController::class, 'status'])->name('status');
        Route::delete('/{id}', [FerramentaController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('orcamentos')->name('orcamentos.')->group(function () {
        Route::get('/', [OrcamentoController::class, 'index'])->name('index');
        Route::get('/listar', [OrcamentoController::class, 'listar'])->name('listar');
        Route::post('/', [OrcamentoController::class, 'store'])->name('store');
        Route::get('/{orcamento}', [OrcamentoController::class, 'show'])->name('show');
        Route::patch('/{orcamento}', [OrcamentoController::class, 'update'])->name('update');
        Route::delete('/{orcamento}', [OrcamentoController::class, 'destroy'])->name('destroy');
        Route::post('/{orcamento}/itens', [OrcamentoController::class, 'addItem'])->name('itens.store');
        Route::patch('/{orcamento}/itens/{item}', [OrcamentoController::class, 'updateItem'])->name('itens.update');
        Route::delete('/{orcamento}/itens/{item}', [OrcamentoController::class, 'removeItem'])->name('itens.destroy');
        Route::get('/{orcamento}/pdf', [OrcamentoController::class, 'pdf'])->name('pdf');
    });
});

require __DIR__.'/auth.php';
