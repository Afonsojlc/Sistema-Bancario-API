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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('nif')->unique();
            $table->date('birth_date'); // Data de nascimento
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('pin_code'); // PIN para aprovar transações
            $table->rememberToken();
            $table->timestamps(); // Cria automaticamente o created_at e updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
