<?php
namespace App\GraphQL\Mutations;
use App\Models\Rol;

class RolMutations {
    public function crear($_, array $args) {
        $rol = Rol::create(['nombre' => $args['nombre']]);
        if (isset($args['permisosIds'])) {
            $rol->permisos()->sync($args['permisosIds']);
        }
        return $rol;
    }
}