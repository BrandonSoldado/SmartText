<?php
namespace App\GraphQL\Mutations;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthMutations {
    public function login($_, array $args) {
        $usuario = Usuario::where('email', $args['email'])->first();
        
        // Verificación manual (ya que usas el campo 'passwords' encriptado)
        if (!$usuario || !Hash::check($args['pass'], $usuario->passwords)) {
            throw new \Exception('Credenciales inválidas');
        }

        $token = JWTAuth::fromUser($usuario);
        return [
            'token' => $token,
            'usuario' => $usuario
        ];
    }
}