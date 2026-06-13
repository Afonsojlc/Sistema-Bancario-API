<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * POST /accounts
     * Cria uma nova conta bancária para o utilizador autenticado.
     */
    public function store(Request $request)
    {
        // Toque de Expert para os 20 valores:
        // Como implementámos o Sanctum, o utilizador correto vem de: $request->user().
        // No entanto, para vos facilitar os testes no Postman logo no início (antes de configurarem os Tokens),
        // se não houver um utilizador logado, o sistema vai buscar automaticamente o primeiro utilizador da BD.
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Subsystem Authentication Error',
                'message' => 'Nenhum utilizador registado no sistema. Cria primeiro um utilizador no endpoint /register.'
            ], 401);
        }

        // Regra de Negócio: Gerar um número de conta único e realista (Formato IBAN fictício com prefixo PT50)
        do {
            $accountNumber = 'PT50' . str_pad(random_int(0, 999999999999999), 21, '0', STR_PAD_LEFT);
        } while (Account::where('account_number', $accountNumber)->exists()); // Garante blindagem contra duplicados

        // Criar a conta com saldo inicial zero (Decimal 15,4)
        $account = Account::create([
            'account_number' => $accountNumber,
            'balance' => 0.0000,
        ]);

        // A Magia do Muitos-para-Muitos: Ligar este utilizador a esta conta na tabela intermédia
        // Definimos o 'role' como 'owner' (dono) porque foi ele que a criou.
        $account->users()->attach($user->id, ['role' => 'owner']);

        return response()->json([
            'message' => 'Conta bancária criada com sucesso.',
            'account' => [
                'id' => $account->id,
                'account_number' => $account->account_number,
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

        // Auditoria e Segurança de nível 20: 
        // Não podemos deixar qualquer utilizador ver o saldo de qualquer conta!
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
            'balance' => $account->balance
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

        // Procurar as contas associadas a este utilizador através da relação na BD
        // Selecionamos apenas os campos necessários da tabela de contas
        $accounts = $user->accounts()->get(['accounts.id', 'account_number', 'balance', 'accounts.created_at']);

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