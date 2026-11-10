<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $id_venta
 * @property int $id_unidad_rollo
 * @property numeric $cantidad
 * @property numeric $precio
 * @property-read \App\Models\Venta $venta
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleVenta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleVenta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleVenta query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleVenta whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleVenta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleVenta whereIdUnidadRollo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleVenta whereIdVenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DetalleVenta wherePrecio($value)
 * @mixin \Eloquent
 */
class DetalleVenta extends Model {
    protected $table = 'detalle_venta';
    protected $guarded = [];
    public $timestamps = false;

    public function venta() {
        return $this->belongsTo(Venta::class, 'id_venta');
    }
}