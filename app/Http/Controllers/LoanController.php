<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoanController extends Controller
{
    /**
     * POST /loans/simulate
     * Calcula as prestações mensais (Sistema Francês) e gera a tabela de amortização.
     */
    public function simulate(Request $request)
    {
        // Validar os dados de entrada no Postman
        $request->validate([
            'amount' => 'required|numeric|gt:0',      // Valor do empréstimo (ex: 10000)
            'term_months' => 'required|integer|gt:0', // Prazo em meses (ex: 24)
            'interest_rate' => 'required|numeric|min:0',// Taxa Anual Nominal (TAN) em % (ex: 5.5)
        ]);

        $principal = (float) $request->amount;
        $termMonths = (int) $request->term_months;
        $annualRate = (float) $request->interest_rate;

        // 1. Converter a Taxa Anual (%) para Taxa Mensal Decimal
        $monthlyInterestRate = ($annualRate / 100) / 12;

        // 2. Calcular o Valor da Prestação Fixa Mensal (Fórmula do Sistema Francês)
        if ($monthlyInterestRate > 0) {
            $monthlyPayment = $principal * ($monthlyInterestRate * pow(1 + $monthlyInterestRate, $termMonths)) / 
                (pow(1 + $monthlyInterestRate, $termMonths) - 1);
        } else {
            // Empréstimo a 0% de juros
            $monthlyPayment = $principal / $termMonths;
        }

        // Arredondar a prestação para cêntimos de forma visual
        $monthlyPayment = round($monthlyPayment, 2);

        // 3. Gerar a Tabela de Amortização Mês a Mês
        $amortizationTable = [];
        $remainingBalance = $principal;
        $totalInterestPaid = 0;

        for ($month = 1; $month <= $termMonths; $month++) {
            // Os juros daquele mês são cobrados sobre o que ainda devemos
            $interestForMonth = round($remainingBalance * $monthlyInterestRate, 2);
            
            // O capital que efetivamente abatemos à dívida este mês
            $principalForMonth = round($monthlyPayment - $interestForMonth, 2);

            // Ajuste no último mês para bater certo ao cêntimo sem saldos negativos
            if ($month === $termMonths) {
                $principalForMonth = $remainingBalance;
                $monthlyPayment = $principalForMonth + $interestForMonth;
                $remainingBalance = 0;
            } else {
                $remainingBalance -= $principalForMonth;
                // Prevenir arredondamentos estranhos (ex: -0.01)
                $remainingBalance = max(0, round($remainingBalance, 2)); 
            }

            $totalInterestPaid += $interestForMonth;

            // Adicionar a linha do mês à tabela
            $amortizationTable[] = [
                'month' => $month,
                'monthly_payment' => number_format($monthlyPayment, 2, '.', ''),
                'interest_paid' => number_format($interestForMonth, 2, '.', ''),
                'principal_paid' => number_format($principalForMonth, 2, '.', ''),
                'remaining_balance' => number_format($remainingBalance, 2, '.', '')
            ];
        }

        // 4. Devolver o JSON final
        return response()->json([
            'simulation_summary' => [
                'loan_amount' => number_format($principal, 2, '.', ''),
                'term_months' => $termMonths,
                'annual_interest_rate_percent' => $annualRate,
                'monthly_payment' => number_format($monthlyPayment, 2, '.', ''),
                'total_interest_paid' => number_format($totalInterestPaid, 2, '.', ''),
                'total_amount_to_pay' => number_format($principal + $totalInterestPaid, 2, '.', '')
            ],
            'amortization_schedule' => $amortizationTable
        ], 200);
    }
}