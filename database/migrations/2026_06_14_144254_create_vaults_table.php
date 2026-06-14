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
        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            // A chave estrangeira que liga este cofre a uma conta bancária específica
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            
            // O nome que o utilizador vai dar ao cofre 
            $table->string('name');

            $table->string('currency', 3);
            
            // O dinheiro que está lá dentro
            $table->decimal('balance', 15, 4)->default(0);
            
            // O objetivo final (para a App poder desenhar uma barra de progresso 0 a 100%)
            $table->decimal('target_amount', 15, 4)->nullable();
            
            // liga ou desliga o arredondamento automático nas compras
            $table->boolean('spare_change_active')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }
};
