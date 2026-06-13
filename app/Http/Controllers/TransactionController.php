<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * POST /accounts/{id}/deposit
     * Efetua um depósito numa conta bancária.
     */
    public function deposit(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0', // Tem de ser maior que zero
        ]);

        $amount = (float) $request->amount;
        $user = $request->user();

        // Usamos DB::transaction para garantir consistência absoluta
        $transactionResult = DB::transaction(function () use ($id, $amount, $user) {
            
            // Removido o lockForUpdate() para compatibilidade com SQLite
            $account = Account::find($id);

            if (!$account) {
                return response()->json(['error' => 'Not Found', 'message' => 'Conta não encontrada.'], 404);
            }

            if (!$account->users()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'error' => 'Access Denied',
                    'message' => 'Não tem permissão para movimentar fundos nesta conta.'
                ], 403); // 403 Forbidden
            }

            // Atualizar o saldo da conta
            $account->balance += $amount;
            $account->save();

            // Gerar referência única
            $reference = 'DEP-' . strtoupper(Str::random(8)) . '-' . time();

            // Registar no histórico (Livro Razão)
            $transaction = Transaction::create([
                'account_id' => $account->id,
                'user_id' => $user ? $user->id : null,
                'reference' => $reference,
                'type' => 'DEPOSIT',
                'amount' => $amount,
                'balance_after' => $account->balance, // Guardamos o saldo histórico exigido
            ]);

            return response()->json([
                'message' => 'Depósito efetuado com sucesso.',
                'transaction_reference' => $transaction->reference,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'new_balance' => $account->balance
            ], 200);
        });

        return $transactionResult;
    }

    /**
     * POST /accounts/{id}/withdraw
     * Efetua um levantamento (valida se o saldo é suficiente).
     */
    public function withdraw(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
        ]);

        $amount = (float) $request->amount;
        $user = $request->user();

        return DB::transaction(function () use ($id, $amount, $user) {
            
            // Removido o lockForUpdate() para compatibilidade com SQLite
            $account = Account::find($id);

            if (!$account) {
                return response()->json(['error' => 'Not Found', 'message' => 'Conta não encontrada.'], 404);
            }
            if (!$account->users()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'error' => 'Access Denied',
                    'message' => 'Não tem permissão para movimentar fundos nesta conta.'
                ], 403); // 403 Forbidden
            }

            // ⚠️ EXIGÊNCIA DO ENUNCIADO: Saldo nunca negativo -> Rejeitado com 422
            if ($account->balance < $amount) {
                return response()->json([
                    'error' => 'Unprocessable Entity',
                    'message' => 'Saldo insuficiente para efetuar o levantamento.'
                ], 422); // HTTP 422 exigido no enunciado!
            }

            // Subtrair o saldo
            $account->balance -= $amount;
            $account->save();

            $reference = 'WTH-' . strtoupper(Str::random(8)) . '-' . time();

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'user_id' => $user ? $user->id : null,
                'reference' => $reference,
                'type' => 'WITHDRAWAL',
                'amount' => $amount,
                'balance_after' => $account->balance,
            ]);

            return response()->json([
                'message' => 'Levantamento efetuado com sucesso.',
                'transaction_reference' => $transaction->reference,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'new_balance' => $account->balance
            ], 200);
        });
    }

    /**
     * POST /transfers
     * Transferência entre contas (atómica: debit + credit)
     */
    public function transfer(Request $request)
    {
        $request->validate([
            'source_account_id' => 'required|exists:accounts,id',
            'destination_account_id' => 'required|exists:accounts,id|different:source_account_id',
            'amount' => 'required|numeric|gt:0',
        ]);

        $sourceId = $request->source_account_id;
        $destinationId = $request->destination_account_id;
        $amount = (float) $request->amount;
        $user = $request->user();

        return DB::transaction(function () use ($sourceId, $destinationId, $amount, $user) {
            // Evitar Deadlocks: Trancar as contas sempre por ordem de ID num sistema bancário
            $ids = [$sourceId, $destinationId];
            sort($ids);
            
            // Removido o lockForUpdate() para compatibilidade com SQLite
            Account::whereIn('id', $ids)->get();

            $sourceAccount = Account::find($sourceId);
            $destinationAccount = Account::find($destinationId);

            // Validar saldo na conta de origem
            if ($sourceAccount->balance < $amount) {
                return response()->json([
                    'error' => 'Unprocessable Entity',
                    'message' => 'Saldo insuficiente na conta de origem.'
                ], 422);
            }

            // Auditoria de Segurança na Transferência: O utilizador é dono da conta de origem?
            if (!$sourceAccount->users()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'error' => 'Access Denied',
                    'message' => 'Não tem permissão para transferir dinheiro a partir desta conta.'
                ], 403);
            }

            // 1. Efetuar o Débito (Tirar dinheiro da conta de origem)
            $sourceAccount->balance -= $amount;
            $sourceAccount->save();

            // 2. Efetuar o Crédito (Meter dinheiro na conta de destino)
            $destinationAccount->balance += $amount;
            $destinationAccount->save();

            // Gerar uma referência partilhada única para a transferência
            $transferRef = 'TRF-' . strtoupper(Str::random(8)) . '-' . time();

            // Criar registo de saída (TRANSFER_OUT) na conta de origem
            Transaction::create([
                'account_id' => $sourceAccount->id,
                'user_id' => $user ? $user->id : null,
                'destination_account_id' => $destinationAccount->id,
                'reference' => $transferRef . '-OUT',
                'type' => 'TRANSFER_OUT',
                'amount' => $amount,
                'balance_after' => $sourceAccount->balance,
            ]);

            // Criar registo de entrada (TRANSFER_IN) na conta de destino
            Transaction::create([
                'account_id' => $destinationAccount->id,
                'user_id' => $user ? $user->id : null,
                'destination_account_id' => null, // Na perspetiva de quem recebe, o destino é ele próprio
                'reference' => $transferRef . '-IN',
                'type' => 'TRANSFER_IN',
                'amount' => $amount,
                'balance_after' => $destinationAccount->balance,
            ]);

            return response()->json([
                'message' => 'Transferência atómica realizada com sucesso.',
                'reference' => $transferRef,
                'amount' => $amount,
                'source_account' => [
                    'id' => $sourceAccount->id,
                    'new_balance' => $sourceAccount->balance
                ],
                'destination_account' => [
                    'id' => $destinationAccount->id,
                    'new_balance' => $destinationAccount->balance
                ]
            ], 200);
        });
    }

    /**
     * GET /accounts/{id}/statement
     * Extrato paginado (com cursor) com filtros dinâmicos por data e tipo.
     */
    public function statement(Request $request, $id)
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json(['error' => 'Not Found', 'message' => 'Conta não encontrada.'], 404);
        }

        // Iniciamos a query ordenada da mais recente para a mais antiga (DESC)
        $query = Transaction::where('account_id', $id)->orderBy('created_at', 'desc');

        // Filtro Dinâmico: Por Tipo de Transação (Ex no Postman: ?type=DEPOSIT)
        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        // Filtro Dinâmico: Por Data de Início (Ex no Postman: ?start_date=2026-05-01)
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->query('start_date'));
        }

        // Filtro Dinâmico: Por Data de Fim (Ex no Postman: ?end_date=2026-05-31)
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->query('end_date'));
        }

        // A exigência de ouro do enunciado: cursorPaginate em vez de paginate
        $transactions = $query->cursorPaginate(10);

        return response()->json($transactions, 200);
    }

    /**
     * GET /accounts/{id}/transactions
     * Lista completa de transações usando apenas a paginação por cursor.
     */
    public function history($id)
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json(['error' => 'Not Found', 'message' => 'Conta não encontrada.'], 404);
        }

        // Aqui não há filtros, é apenas o histórico contínuo paginado por cursor
        $transactions = Transaction::where('account_id', $id)
                            ->orderBy('created_at', 'desc')
                            ->cursorPaginate(15); // Mostra 15 transações de cada vez

        return response()->json($transactions, 200);
    }
}