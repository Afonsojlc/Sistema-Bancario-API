<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // A conta principal onde ocorreu o movimento
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            
            // Quem fez o movimento (nullable, pois pode ser um débito automático do banco)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // A conta de destino (só preenchida se for transferência)
            $table->foreignId('destination_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            
            $table->string('reference')->unique(); // Ex: TRX-2026-ABC
            $table->string('type'); // 'DEPOSIT', 'WITHDRAW', 'TRANSFER'
            $table->decimal('amount', 15, 4);
            
            // Exigência do enunciado: Saldo após a operação
            $table->decimal('balance_after', 15, 4);
            
            // Numa transação, só existe data de criação. Finanças não se "atualizam", anulam-se.
            $table->timestamp('created_at')->useCurrent(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
