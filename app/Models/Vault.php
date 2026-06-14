<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Account;

class Vault extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'name',
        'currency',
        'balance',
        'target_amount',
        'spare_change_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:4',
            'target_amount' => 'decimal:4',
            'spare_change_active' => 'boolean', 
        ];
    }

    /**
     * RELAÇÃO: Um cofre pertence sempre a uma Conta Bancária principal.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}