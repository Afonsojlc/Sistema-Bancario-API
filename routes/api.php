<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\VaultController;

/*
|--------------------------------------------------------------------------
| Rotas da API do Sistema Bancário
|--------------------------------------------------------------------------
*/

// 1. ROTAS PÚBLICAS (Não precisam de Token)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// 2. ROTAS PRIVADAS (Exigem Token de Autenticação - Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // Gestão de Contas
    Route::prefix('accounts')->group(function () {
        Route::get('/my-accounts', [AccountController::class, 'myAccounts']);
        Route::post('/', [AccountController::class, 'store']);
        Route::get('/{id}/balance', [AccountController::class, 'balance']);
        
        // Movimentos
        Route::post('/{id}/deposit', [TransactionController::class, 'deposit']);
        Route::post('/{id}/withdraw', [TransactionController::class, 'withdraw']);
        
        // Extratos e Histórico
        Route::get('/{id}/statement', [TransactionController::class, 'statement']);
        Route::get('/{id}/transactions', [TransactionController::class, 'history']);

        Route::post('/{accountId}/vaults', [VaultController::class, 'store']);      
    });
    
    Route::get('/vaults/my-vaults', [VaultController::class, 'myVaults']);
    Route::post('/vaults/{id}/deposit', [VaultController::class, 'deposit']);
    Route::post('/vaults/{id}/withdraw', [VaultController::class, 'withdraw']);

    // Transferências
    Route::post('/transfers', [TransactionController::class, 'transfer']);

    // Simulador de Empréstimo (Podes manter público se quiseres, mas faz sentido privado num banco)
    Route::post('/loans/simulate', [LoanController::class, 'simulate']);


    
});