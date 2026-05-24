<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Regista um novo cliente no banco
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6',
            'nif' => 'required|string|size:9|unique:users,nif',
            'birth_date' => 'required|date|before:today',
            'pin_code' => 'required|digits:4', // O PIN tem de ter exatos 4 dígitos numéricos
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'nif' => $validated['nif'],
            'birth_date' => $validated['birth_date'],
            'pin_code' => Hash::make($validated['pin_code']),
        ]);

        // Cria o token de segurança para o Postman
        $token = $user->createToken('bank-api-token')->plainTextToken;

        return response()->json([
            'message' => 'Cliente registado com sucesso.',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    // Faz Login num cliente existente
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // Verifica se o user existe e se a password bate certo
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais estão incorretas.'],
            ]);
        }

        $token = $user->createToken('bank-api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login efetuado com sucesso.',
            'token' => $token
        ]);
    }
}