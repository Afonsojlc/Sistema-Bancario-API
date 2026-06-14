<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_number',
        'balance',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:4', 
        ];
    }

    /**
     * RELAÇÕES
     */
    // Uma conta tem vários donos/utilizadores 
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }

    // Uma conta tem muitas transações
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Uma conta pode ter vários Cofres 
    public function vaults()
    {
        return $this->hasMany(Vault::class);
    }
}