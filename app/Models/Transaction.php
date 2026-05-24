<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    // Desligamos o updated_at porque uma transação nunca pode ser alterada depois de feita!
    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'user_id',
        'destination_account_id',
        'reference',
        'type',
        'amount',
        'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }

    /**
     * RELAÇÕES
     */
    // A conta principal a que esta transação pertence
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    // Quem fez a transação (Opcional, pois pode ser um débito direto)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // A conta de destino (Só existe se for transferência)
    public function destinationAccount()
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }
}