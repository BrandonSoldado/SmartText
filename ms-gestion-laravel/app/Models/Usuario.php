<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property int $id_rol
 * @property string $nombres
 * @property string|null $apellido_p
 * @property string|null $apellido_m
 * @property string $email
 * @property string $passwords
 * @property string|null $foto
 * @property string|null $telefono
 * @property string|null $fecha_nacimiento
 * @property string|null $stripe_customer_id
 * @property-read \App\Models\Rol $rol
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereApellidoM($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereApellidoP($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereFechaNacimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereFoto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereIdRol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereNombres($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario wherePasswords($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereStripeCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Usuario whereTelefono($value)
 * @mixin \Eloquent
 */
class Usuario extends Authenticatable implements JWTSubject {
    protected $table = 'usuario';
    protected $guarded = [];
    public $timestamps = false;

    // Métodos para JWT Auth
    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return ['id_rol' => $this->id_rol]; }

    public function rol() {
        return $this->belongsTo(Rol::class, 'id_rol');
    }
}