<?php
namespace App\Services;

use App\Models\Venta;
use App\Models\DetalleVenta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class SagaPedidoService
{
    private $urlInventario;
    private $urlPagos;

    public function __construct()
    {
        // Se inyectan las variables de entorno al instanciar el servicio
        $this->urlInventario = env('MS_INVENTARIO_URL');
        $this->urlPagos = env('MS_PAGOS_URL');
    }

    // MODIFICACIÓN 1: Agregamos $token a la firma de la función
    public function ejecutarSaga(string $idUsuario, array $detallesInput, string $idempotencyKey = null, string $token = null): Venta
    {
        DB::beginTransaction();
        try {
            // 1. MS GESTIÓN (LARAVEL): Crear Venta PENDIENTE localmente
            $total = 0;
            $rollosParaInventario = [];

            foreach ($detallesInput as $d) {
                $total += ($d['cantidad'] * $d['precio']);
                $rollosParaInventario[] = [
                    'idUnidadRollo' => $d['id_unidad_rollo'], // <-- CORREGIDO AQUI
                    'cantidad' => $d['cantidad']
                ];
            }

            $venta = Venta::create([
                'id_usuario' => $idUsuario,
                'total' => $total,
                'fecha' => date('Y-m-d'),
                'hora' => date('H:i:s'),
                'estado' => 'PENDIENTE'
            ]);

            foreach ($detallesInput as $d) {
                DetalleVenta::create([
                    'id_venta' => $venta->id,
                    'id_unidad_rollo' => $d['id_unidad_rollo'], // <-- CORREGIDO AQUI
                    'cantidad' => $d['cantidad'],
                    'precio' => $d['precio']
                ]);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            // Mostrará el error exacto si falla la base de datos local
            throw new Exception("Fallo local en MS Gestión: " . $e->getMessage()); 
        }

        // 2. MS INVENTARIO (SPRING BOOT): Reservar stock
        $respInventario = Http::post("{$this->urlInventario}/reserva", [
            'idVenta' => $venta->id,
            'rollos' => $rollosParaInventario
        ]);

        if (!$respInventario->successful()) {
            $this->cancelarVenta($venta, 'Stock insuficiente o error en Inventario');
            throw new Exception("SAGA FALLIDA: Stock insuficiente.");
        }

        // 3. MS PAGOS (NESTJS): Intentar cobrar
        if (!$idempotencyKey) {
            $idempotencyKey = (string) Str::uuid(); // Genera un UUID si no se proporciona uno
        }

        // MODIFICACIÓN 2: Preparamos la petición HTTP inyectando el Token JWT
        $clienteHttp = Http::asJson();
        if ($token) {
            $clienteHttp = $clienteHttp->withToken($token);
        }

        $respPagos = $clienteHttp->post("{$this->urlPagos}/procesar", [
            'id_venta' => $venta->id,
            'monto' => $total,
            'idempotency_key' => $idempotencyKey // Previene cobros dobles
        ]);

        // MODIFICACIÓN 3: Capturamos el error exacto de NestJS si falla
        if (!$respPagos->successful()) {
            // ROLLBACK: Si el pago falla, liberamos el stock en Spring Boot
            Http::delete("{$this->urlInventario}/reserva/venta/{$venta->id}");
            $this->cancelarVenta($venta, 'Pago rechazado o servicio caído');
            
            // Extraemos la respuesta real de NestJS para saber por qué rechazó el cobro
            $errorNestJS = $respPagos->body();
            throw new Exception("SAGA FALLIDA. Motivo de NestJS: " . $errorNestJS);
        }

        // 4. ÉXITO DE LA SAGA: Actualizar estado
        $venta->estado = 'COMPLETADO';
        $venta->save();

        return $venta;
    }

    private function cancelarVenta(Venta $venta, string $motivo)
    {
        $venta->estado = 'CANCELADO';
        $venta->motivo_cancelacion = $motivo;
        $venta->save();
    }
}