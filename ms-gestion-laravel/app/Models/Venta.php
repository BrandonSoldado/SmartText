<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $id_usuario
 * @property numeric $total
 * @property string $fecha
 * @property string $hora
 * @property string $estado
 * @property string|null $motivo_cancelacion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DetalleVenta> $detalles
 * @property-read int|null $detalles_count
 * @property-read \App\Models\Usuario $usuario
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereHora($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereIdUsuario($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereMotivoCancelacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Venta whereTotal($value)
 * @mixin \Eloquent
 */
class Venta extends Model {
    protected $table = 'venta';
    protected $guarded = [];
    public $timestamps = false;

    public function detalles() {
        return $this->hasMany(DetalleVenta::class, 'id_venta');
    }
    
    public function usuario() {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}