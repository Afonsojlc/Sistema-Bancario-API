<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AccountController extends Controller
{
    /**
     * POST /accounts
     * Cria uma nova conta bancária para o utilizador autenticado.
     */
    public function store(Request $request)
    {
        
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Subsystem Authentication Error',
                'message' => 'Nenhum utilizador registado no sistema. Cria primeiro um utilizador no endpoint /register.'
            ], 401);
        }

        $validCurrencies = Cache::remember('valid_currencies', 86400, function () {
            $response = Http::get('https://api.frankfurter.app/currencies');
            
            if ($response->successful()) {
                // Devolve um array só com as siglas das moedas válidas (EUR, USD, GBP, etc.)
                return array_keys($response->json());
            }

            // Se a API do BCE falhar hoje, permitimos pelo menos estas
            return ['EUR', 'USD', 'GBP', 'CHF', 'JPY', 'AUD', 'CAD'];
        });

        // Vai Transformar o array numa string separada por vírgulas para o Laravel validar
        $validCurrenciesString = implode(',', $validCurrencies);

        $validated = $request->validate([
            'currency' => "nullable|string|size:3|in:{$validCurrenciesString}"
        ]);

        // Vair criar um número de conta único e realista (Formato IBAN fictício com prefixo PT50)
        do {
            $randomDigits = '';
            for ($i = 0; $i < 15; $i++) {
                $randomDigits .= random_int(0, 9);
            }
            
            // Junta o PT50 com os 15 números
            $accountNumber = 'PT50' . str_pad($randomDigits, 21, '0', STR_PAD_LEFT);
            
        } while (Account::where('account_number', $accountNumber)->exists());

        $currency = $validated['currency'] ?? 'EUR';

        // Criar a conta com saldo inicial zero 
        $account = Account::create([
            'account_number' => $accountNumber,
            'balance' => 0.0000,
            'currency' => $currency
        ]);

        // Ligar este utilizador a esta conta na tabela intermédia
        // Definimos o 'role' como 'owner' (dono) porque foi ele que a criou.
        $account->users()->attach($user->id, ['role' => 'owner']);

        return response()->json([
            'message' => 'Conta bancária criada com sucesso.',
            'account' => [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'currency' => $account->currency,
                'balance' => $account->balance,
                'created_at' => $account->created_at,
            ],
            'owner' => [
                'id' => $user->id,
                'name' => $user->name,
                'nif' => $user->nif
            ]
        ], 201);
    }

    /**
     * GET /accounts/{id}/balance
     * Retorna o saldo atual da conta especificada.
     */
    public function balance(Request $request, $id)
    {
        // Procurar a conta na BD
        $account = Account::find($id);

        if (!$account) {
            return response()->json([
                'error' => 'Resource Not Found',
                'message' => 'A conta bancária especificada não existe.'
            ], 404);
        }

        // Verifica se o utilizador autenticado tem permissão para ver o saldo desta conta
        $user = $request->user();
        
        if ($user && !$account->users()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'error' => 'Access Denied',
                'message' => 'Não tem permissão para consultar o saldo desta conta bancária.'
            ], 403);
        }

        return response()->json([
            'account_id' => $account->id,
            'account_number' => $account->account_number,
            'currency' => $account->currency, 
            'balance' => $account->balance,
            'formatted_balance' => number_format($account->balance, 2) . ' ' . $account->currency
        ], 200);
    }

    /**
     * GET /accounts/my-accounts
     * Lista todas as contas bancárias associadas ao utilizador autenticado.
     */
    public function myAccounts(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $accounts = $user->accounts()->get(['accounts.id', 'account_number', 'currency', 'balance', 'accounts.created_at']);

        $accounts->map(function ($account) {
            $account->formatted_balance = number_format($account->balance, 2) . ' ' . $account->currency;
            return $account;
        });

        return response()->json([
            'message' => 'Contas bancárias recuperadas com sucesso.',
            'total_accounts' => $accounts->count(),
            'owner' => [
                'id' => $user->id,
                'name' => $user->name,
                'nif' => $user->nif
            ],
            'accounts' => $accounts
        ], 200);
    }
}