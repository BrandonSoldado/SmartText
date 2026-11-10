<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permiso newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permiso newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permiso query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permiso whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permiso whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Permiso whereNombre($value)
 * @mixin \Eloquent
 */
class Permiso extends Model {
    protected $table = 'permiso';
    protected $guarded = [];
    public $timestamps = false;
}