<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nif',          // Adicionado para os 20 valores
        'birth_date',   // Adicionado para os 20 valores
        'pin_code',     // Adicionado para os 20 valores
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'pin_code',     // NUNCA devolver o PIN na API!
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_code' => 'hashed', // O PIN também deve ser encriptado como a password
            'birth_date' => 'date', // Garante que é tratado como data
        ];
    }

    /**
     * RELAÇÕES (A magia do Muitos-para-Muitos que falámos!)
     */
    public function accounts()
    {
        return $this->belongsToMany(Account::class)->withPivot('role');
    }
}