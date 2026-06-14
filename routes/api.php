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
| A estrutura abaixo espelha a documentação e os testes do Postman.
*/

// ========================================================================
// 1. AUTENTICAÇÃO (Rotas Públicas)
// ========================================================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// ========================================================================
// MOTOR BANCÁRIO (Rotas Privadas - Protegidas por Token Sanctum)
// ========================================================================
Route::middleware('auth:sanctum')->group(function () {
    
    // --------------------------------------------------------------------
    // 2. Gestão de Contas
    // --------------------------------------------------------------------
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::get('/accounts/my-accounts', [AccountController::class, 'myAccounts']);
    Route::get('/accounts/{id}/balance', [AccountController::class, 'balance']);
    
    // --------------------------------------------------------------------
    // 3. Operações Financeiras (Movimentos)
    // --------------------------------------------------------------------
    Route::post('/accounts/{id}/deposit', [TransactionController::class, 'deposit']);
    Route::post('/accounts/{id}/withdraw', [TransactionController::class, 'withdraw']);
    Route::post('/transfers', [TransactionController::class, 'transfer']);
    Route::post('/accounts/{id}/payment', [TransactionController::class, 'payment']);
    
    // --------------------------------------------------------------------
    // 4. Extratos e Auditorias
    // --------------------------------------------------------------------
    Route::get('/accounts/{id}/transactions', [TransactionController::class, 'history']);
    Route::get('/accounts/{id}/statement', [TransactionController::class, 'statement']);

    // --------------------------------------------------------------------
    // 5. Crédito e Simuladores
    // --------------------------------------------------------------------
    // Mantemos protegido, garantindo que só clientes do banco podem simular
    Route::post('/loans/simulate', [LoanController::class, 'simulate']);

    // --------------------------------------------------------------------
    // 6. Cofres de Poupança (Vaults & Spare Change)
    // --------------------------------------------------------------------
    Route::post('/accounts/{accountId}/vaults', [VaultController::class, 'store']);
    Route::get('/vaults/my-vaults', [VaultController::class, 'myVaults']);
    Route::patch('/vaults/{id}/spare-change', [VaultController::class, 'toggleSpareChange']);
    Route::post('/vaults/{id}/deposit', [VaultController::class, 'deposit']);
    Route::post('/vaults/{id}/withdraw', [VaultController::class, 'withdraw']);
    
});