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
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('destination_account_id')->nullable()->constrained('accounts');
            $table->string('reference')->unique();
            $table->string('type'); 
            
            $table->decimal('amount', 15, 4); 
            
            $table->decimal('original_amount', 15, 4)->nullable(); 
            $table->string('original_currency', 3)->nullable();    

            $table->decimal('balance_after', 15, 4);
            $table->timestamps();
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
