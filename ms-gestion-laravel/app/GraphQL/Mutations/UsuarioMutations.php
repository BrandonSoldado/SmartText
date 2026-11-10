<?php
namespace App\GraphQL\Mutations;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class UsuarioMutations {
    public function crear($_, array $args) {
        // Encriptar password antes de guardar
        $args['passwords'] = Hash::make($args['passwords']);
        return Usuario::create($args);
    }
}