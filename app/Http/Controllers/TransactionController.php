<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TransactionController extends Controller
{
    /**
     * POST /accounts/{id}/deposit
     */
    public function deposit(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'currency' => 'nullable|string|size:3'
        ]);

        $amount = (float) $request->amount;
        $inputCurrency = strtoupper($request->input('currency', ''));
        
        /** @var \App\Models\User $user */
        $user = $request->user();

        $transactionResult = DB::transaction(function () use ($id, $amount, $inputCurrency, $user) {
            
            $account = Account::find($id);

            if (!$account) {
                return response()->json(['error' => 'Not Found', 'message' => 'Conta não encontrada.'], 404);
            }

            if (!$account->users()->where('user_id', $user->id)->exists()) {
                return response()->json(['error' => 'Access Denied', 'message' => 'Sem permissão.'], 403);
            }

            $accountCurrency = $account->currency ?? 'EUR';
            if (empty($inputCurrency)) {
                $inputCurrency = $accountCurrency; 
            }

            $convertedAmount = $amount;
            $appliedRate = 1.00;

            if ($inputCurrency !== $accountCurrency) {
                $cacheKey = "exchange_rate_{$inputCurrency}_{$accountCurrency}";
                $appliedRate = Cache::remember($cacheKey, 3600, function () use ($inputCurrency, $accountCurrency) {
                    $response = Http::get("https://api.frankfurter.app/latest", ['from' => $inputCurrency, 'to' => $accountCurrency]);
                    if ($response->successful()) return $response->json()['rates'][$accountCurrency];
                    abort(503, 'Serviço de câmbios indisponível.');
                });
                $convertedAmount = $amount * $appliedRate;
            }

            $account->balance += $convertedAmount;
            $account->save();

            $reference = 'DEP-' . strtoupper(Str::random(8)) . '-' . time();

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'user_id' => $user ? $user->id : null,
                'reference' => $reference,
                'type' => 'DEPOSIT',
                'amount' => $convertedAmount,           
                'original_amount' => $amount,           
                'original_currency' => $inputCurrency,  
                'balance_after' => $account->balance,
            ]);

            return response()->json([
                'message' => 'Depósito efetuado com sucesso.',
                'transaction_reference' => $transaction->reference,
                'type' => $transaction->type,
                'exchange_info' => [
                    'is_cross_currency' => $inputCurrency !== $accountCurrency,
                    'rate_applied' => round($appliedRate, 4),
                    'original_amount_deposited' => number_format($amount, 2) . ' ' . $inputCurrency,
                    'converted_amount_credited' => number_format($convertedAmount, 2) . ' ' . $accountCurrency,
                ],
                'account_currency' => $accountCurrency,
                'formatted_amount_credited' => '+ ' . number_format($convertedAmount, 2) . ' ' . $accountCurrency,
                'new_balance' => $account->balance,
                'formatted_balance' => number_format($account->balance, 2) . ' ' . $accountCurrency
            ], 200);
        });

        return $transactionResult;
    }

    /**
     * POST /accounts/{id}/withdraw
     */
    public function withdraw(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'currency' => 'nullable|string|size:3'
        ]);

        $amount = (float) $request->amount;
        $inputCurrency = strtoupper($request->input('currency', ''));

        /** @var \App\Models\User $user */
        $user = $request->user();

        return DB::transaction(function () use ($id, $amount, $inputCurrency, $user) {
            
            $account = Account::find($id);

            if (!$account) return response()->json(['error' => 'Not Found', 'message' => 'Conta não encontrada.'], 404);
            if (!$account->users()->where('user_id', $user->id)->exists()) return response()->json(['error' => 'Access Denied', 'message' => 'Sem permissão.'], 403);

            $accountCurrency = $account->currency ?? 'EUR';
            if (empty($inputCurrency)) $inputCurrency = $accountCurrency; 

            $convertedAmountToDeduct = $amount;
            $appliedRate = 1.00;

            if ($inputCurrency !== $accountCurrency) {
                $cacheKey = "exchange_rate_{$inputCurrency}_{$accountCurrency}";
                $appliedRate = Cache::remember($cacheKey, 3600, function () use ($inputCurrency, $accountCurrency) {
                    $response = Http::get("https://api.frankfurter.app/latest", ['from' => $inputCurrency, 'to' => $accountCurrency]);
                    if ($response->successful()) return $response->json()['rates'][$accountCurrency];
                    abort(503, 'Serviço de câmbios indisponível.');
                });
                $convertedAmountToDeduct = $amount * $appliedRate;
            }

            if ($account->balance < $convertedAmountToDeduct) {
                return response()->json(['error' => 'Unprocessable Entity', 'message' => 'Saldo insuficiente após câmbio.'], 422);
            }

            $account->balance -= $convertedAmountToDeduct;
            $account->save();

            $reference = 'WTH-' . strtoupper(Str::random(8)) . '-' . time();

            $transaction = Transaction::create([
                'account_id' => $account->id,
                'user_id' => $user ? $user->id : null,
                'reference' => $reference,
                'type' => 'WITHDRAWAL',
                'amount' => $convertedAmountToDeduct,
                'original_amount' => $amount,          
                'original_currency' => $inputCurrency, 
                'balance_after' => $account->balance,
            ]);

            return response()->json([
                'message' => 'Levantamento efetuado com sucesso.',
                'transaction_reference' => $transaction->reference,
                'type' => $transaction->type,
                'exchange_info' => [
                    'is_cross_currency' => $inputCurrency !== $accountCurrency,
                    'rate_applied' => round($appliedRate, 4),
                    'requested_withdrawal' => number_format($amount, 2) . ' ' . $inputCurrency,
                    'converted_amount_debited' => number_format($convertedAmountToDeduct, 2) . ' ' . $accountCurrency,
                ],
                'account_currency' => $accountCurrency,
                'formatted_amount_debited' => '- ' . number_format($convertedAmountToDeduct, 2) . ' ' . $accountCurrency,
                'new_balance' => $account->balance,
                'formatted_balance' => number_format($account->balance, 2) . ' ' . $accountCurrency
            ], 200);
        });
    }

    /**
     * POST /transfers
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
        /** @var \App\Models\User $user */
        $user = $request->user();

        return DB::transaction(function () use ($sourceId, $destinationId, $amount, $user) {
            
            $ids = [$sourceId, $destinationId];
            sort($ids);
            Account::whereIn('id', $ids)->get();

            $sourceAccount = Account::find($sourceId);
            $destinationAccount = Account::find($destinationId);

            if ($sourceAccount->balance < $amount) return response()->json(['error' => 'Unprocessable Entity', 'message' => 'Saldo insuficiente.'], 422);
            if (!$sourceAccount->users()->where('user_id', $user->id)->exists()) return response()->json(['error' => 'Access Denied', 'message' => 'Sem permissão.'], 403);

            $sourceCurrency = $sourceAccount->currency ?? 'EUR';
            $destCurrency = $destinationAccount->currency ?? 'EUR';

            $convertedAmount = $amount;
            $appliedRate = 1.00;

            if ($sourceCurrency !== $destCurrency) {
                $cacheKey = "exchange_rate_{$sourceCurrency}_{$destCurrency}";
                $appliedRate = Cache::remember($cacheKey, 3600, function () use ($sourceCurrency, $destCurrency) {
                    $response = Http::get("https://api.frankfurter.app/latest", ['from' => $sourceCurrency, 'to' => $destCurrency]);
                    if ($response->successful()) return $response->json()['rates'][$destCurrency];
                    abort(503, 'Serviço indisponível.');
                });
                $convertedAmount = $amount * $appliedRate;
            }

            $sourceAccount->balance -= $amount;
            $sourceAccount->save();

            $destinationAccount->balance += $convertedAmount;
            $destinationAccount->save();

            $transferRef = 'TRF-' . strtoupper(Str::random(8)) . '-' . time();

            // 1. Registo de Saída: O "destino" desta transação é a conta de destino
            Transaction::create([
                'account_id' => $sourceAccount->id,
                'user_id' => $user ? $user->id : null,
                'destination_account_id' => $destinationAccount->id, // Conta que vai receber
                'reference' => $transferRef . '-OUT',
                'type' => 'TRANSFER_OUT',
                'amount' => $amount,
                'original_amount' => $amount,
                'original_currency' => $sourceCurrency,
                'balance_after' => $sourceAccount->balance,
            ]);

            // 2. Registo de Entrada: O "destino" desta vez atua como a origem da transferência!
            Transaction::create([
                'account_id' => $destinationAccount->id,
                'user_id' => $user ? $user->id : null,
                'destination_account_id' => $sourceAccount->id, // Conta de onde veio o dinheiro!
                'reference' => $transferRef . '-IN',
                'type' => 'TRANSFER_IN',
                'amount' => $convertedAmount,
                'original_amount' => $amount,           
                'original_currency' => $sourceCurrency, 
                'balance_after' => $destinationAccount->balance,
            ]);

            $destUser = $destinationAccount->users()->first();
            $destName = $destUser ? $destUser->name : 'Desconhecido';

            return response()->json([
                'message' => 'Transferência realizada com sucesso.',
                'reference' => $transferRef,
                'exchange_info' => [
                    'is_cross_currency' => $sourceCurrency !== $destCurrency,
                    'rate_applied' => round($appliedRate, 4),
                    'amount_debited' => number_format($amount, 2) . ' ' . $sourceCurrency,
                    'amount_credited' => number_format($convertedAmount, 2) . ' ' . $destCurrency,
                ],
                // AQUI ESTÁ O TEU TALÃO COMPLETO:
                'source_account' => [
                    'id' => $sourceAccount->id,
                    'account_number' => $sourceAccount->account_number,
                    'owner_name' => $user->name, // Titular que enviou (o utilizador autenticado)
                    'currency' => $sourceCurrency,
                    'new_balance' => $sourceAccount->balance,
                    'formatted_balance' => number_format($sourceAccount->balance, 2) . ' ' . $sourceCurrency
                ],
                'destination_account' => [
                    'id' => $destinationAccount->id,
                    'account_number' => $destinationAccount->account_number,
                    'owner_name' => $destName, // Titular que recebeu a transferência
                    'currency' => $destCurrency
                ]
            ], 200);
        });
    }

    /**
     * GET /accounts/{id}/statement
     */
    public function statement(Request $request, $id)
    {
        $account = Account::find($id);

        if (!$account) return response()->json(['error' => 'Not Found', 'message' => 'Conta não encontrada.'], 404);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$account->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'Access Denied', 'message' => 'Sem permissão.'], 403);
        }

        // CARREGA A CONTA DE DESTINO E OS SEUS TITULARES PARA EVITAR QUERIES A MAIS!
        $query = Transaction::with(['destinationAccount.users'])->where('account_id', $id)->orderBy('created_at', 'desc');

        if ($request->has('type')) $query->where('type', $request->query('type'));
        if ($request->has('start_date')) $query->whereDate('created_at', '>=', $request->query('start_date'));
        if ($request->has('end_date')) $query->whereDate('created_at', '<=', $request->query('end_date'));

        $transactions = $query->cursorPaginate(10);
        
        $transactions->through(function ($transaction) use ($account) {
            return $this->formatTransactionForStatement($transaction, $account);
        });

        return response()->json($transactions, 200);
    }

    /**
     * GET /accounts/{id}/transactions
     */
    public function history(Request $request, $id)
    {
        $account = Account::find($id);

        if (!$account) return response()->json(['error' => 'Not Found', 'message' => 'Conta não encontrada.'], 404);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$account->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'Access Denied', 'message' => 'Sem permissão.'], 403);
        }
        
        // CARREGA A CONTA DE DESTINO E OS SEUS TITULARES
        $transactions = Transaction::with(['destinationAccount.users'])
                            ->where('account_id', $id)
                            ->orderBy('created_at', 'desc')
                            ->cursorPaginate(15); 

        $transactions->through(function ($transaction) use ($account) {
            return $this->formatTransactionForStatement($transaction, $account);
        });

        return response()->json($transactions, 200);
    }

    /**
     * Helper privado para embelezar as transações do Extrato (Mantendo código limpo/DRY)
     */
    private function formatTransactionForStatement($transaction, $account)
    {
        $currency = $account->currency ?? 'EUR';
        $prefix = in_array($transaction->type, ['DEPOSIT', 'TRANSFER_IN']) ? '+ ' : '- ';

        $transaction->currency = $currency;
        
        $baseFormat = $prefix . number_format($transaction->amount, 2) . ' ' . $currency;
        
        if ($transaction->original_currency && $transaction->original_currency !== $currency) {
            $transaction->formatted_amount = $baseFormat . ' (via ' . number_format($transaction->original_amount, 2) . ' ' . $transaction->original_currency . ')';
        } else {
            $transaction->formatted_amount = $baseFormat;
        }

        $transaction->formatted_balance_after = number_format($transaction->balance_after, 2) . ' ' . $currency;

        // --- A GRANDE NOVIDADE: Titulares e Descrição (Revolut Style) ---
        if (in_array($transaction->type, ['TRANSFER_OUT', 'TRANSFER_IN']) && $transaction->destinationAccount) {
            $counterpart = $transaction->destinationAccount;
            $counterpartUser = $counterpart->users->first();
            $counterpartName = $counterpartUser ? $counterpartUser->name : 'Desconhecido';

            // Dados crus para quem vai programar o ecrã do telemóvel
            $transaction->counterpart_info = [
                'name' => $counterpartName,
                'account_number' => $counterpart->account_number
            ];

            // A string final e perfeita que o utilizador vai ler no extrato
            if ($transaction->type === 'TRANSFER_OUT') {
                $transaction->description = 'Transferência enviada para ' . $counterpartName;
            } else {
                $transaction->description = 'Transferência recebida de ' . $counterpartName;
            }
        } else {
            $titles = [
                'DEPOSIT' => 'Depósito de Fundos',
                'WITHDRAWAL' => 'Levantamento no Multibanco'
            ];
            $transaction->description = $titles[$transaction->type] ?? $transaction->type;
        }

        // Limpamos o objeto sujo da base de dados para o JSON final ficar limpo
        unset($transaction->destinationAccount);

        return $transaction;
    }
}