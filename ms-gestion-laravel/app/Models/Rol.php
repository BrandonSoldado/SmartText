<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permiso> $permisos
 * @property-read int|null $permisos_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rol newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rol newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rol query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rol whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rol whereNombre($value)
 * @mixin \Eloquent
 */
class Rol extends Model {
    protected $table = 'rol';
    protected $guarded = [];
    public $timestamps = false;

    public function permisos() {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'id_rol', 'id_permiso');
    }
}