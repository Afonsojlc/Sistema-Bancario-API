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
        // Adicionado a validação opcional da Moeda
        $request->validate([
            'amount' => 'required|numeric|gt:0',      
            'term_months' => 'required|integer|gt:0', 
            'interest_rate' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3'
        ]);

        $principal = (float) $request->amount;
        $termMonths = (int) $request->term_months;
        $annualRate = (float) $request->interest_rate;
        
        // Se a moeda não for enviada, assume EUR por defeito
        $currency = strtoupper($request->input('currency', 'EUR'));

        $monthlyInterestRate = ($annualRate / 100) / 12;

        if ($monthlyInterestRate > 0) {
            $monthlyPayment = $principal * ($monthlyInterestRate * pow(1 + $monthlyInterestRate, $termMonths)) / 
                (pow(1 + $monthlyInterestRate, $termMonths) - 1);
        } else {
            $monthlyPayment = $principal / $termMonths;
        }

        $amortizationTable = [];
        $remainingBalance = $principal;
        $totalInterestPaid = 0;

        for ($month = 1; $month <= $termMonths; $month++) {
            $interestForMonth = $remainingBalance * $monthlyInterestRate;
            $principalForMonth = $monthlyPayment - $interestForMonth;

            if ($month === $termMonths) {
                $principalForMonth = $remainingBalance;
                $monthlyPayment = $principalForMonth + $interestForMonth;
                $remainingBalance = 0;
            } else {
                $remainingBalance -= $principalForMonth;
                $remainingBalance = max(0, round($remainingBalance, 2)); 
            }

            $totalInterestPaid += $interestForMonth;

            // Formatação com a moeda escolhida
            $amortizationTable[] = [
                'month' => $month,
                'monthly_payment' => number_format($monthlyPayment, 2, '.', '') . ' ' . $currency,
                'interest_paid' => number_format($interestForMonth, 2, '.', '') . ' ' . $currency,
                'principal_paid' => number_format($principalForMonth, 2, '.', '') . ' ' . $currency,
                'remaining_balance' => number_format($remainingBalance, 2, '.', '') . ' ' . $currency
            ];
        }

        return response()->json([
            'simulation_summary' => [
                'loan_amount' => number_format($principal, 2, '.', '') . ' ' . $currency,
                'term_months' => $termMonths,
                'annual_interest_rate_percent' => $annualRate . '%',
                'monthly_payment' => number_format($monthlyPayment, 2, '.', '') . ' ' . $currency,
                'total_interest_paid' => number_format($totalInterestPaid, 2, '.', '') . ' ' . $currency,
                'total_amount_to_pay' => number_format($principal + $totalInterestPaid, 2, '.', '') . ' ' . $currency
            ],
            'amortization_schedule' => $amortizationTable
        ], 200);
    }
}