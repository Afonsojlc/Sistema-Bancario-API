<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\LoanController;

/*
|--------------------------------------------------------------------------
| Rotas da API do Sistema Bancário
|--------------------------------------------------------------------------
*/

// Agrupamos tudo o que precisa de um "utilizador autenticado" (Exigência do enunciado)
// Para testarmos rápido no início, vamos deixar fora do middleware auth:sanctum, 
// mas já com a estrutura pronta para os 20 valores.
// Adiciona esta linha lá em cima junto aos outros "use":
use App\Http\Controllers\AuthController;

// Adiciona estas linhas antes das rotas das accounts:
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::prefix('accounts')->group(function () {
    // Criar conta
    Route::post('/', [AccountController::class, 'store']);
    
    // Saldo atual
    Route::get('/{id}/balance', [AccountController::class, 'balance']);
    
    // Depósito e Levantamento
    Route::post('/{id}/deposit', [TransactionController::class, 'deposit']);
    Route::post('/{id}/withdraw', [TransactionController::class, 'withdraw']);
    
    // Extratos e Histórico
    Route::get('/{id}/statement', [TransactionController::class, 'statement']);
    Route::get('/{id}/transactions', [TransactionController::class, 'history']);

    
});

// Transferências
Route::post('/transfers', [TransactionController::class, 'transfer']);

// Simulador de Empréstimo
Route::post('/loans/simulate', [LoanController::class, 'simulate']);