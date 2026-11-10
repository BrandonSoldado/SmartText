<?php
namespace App\GraphQL\Mutations;

use App\Models\Venta;
use App\Models\DetalleVenta;
use GraphQL\Error\Error;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SagaVentasMutations {
    public function crear($_, array $args, GraphQLContext $context) {
        $venta = null;
        $reservaIntentada = false;
        $paso = 'Gestión';
        $urlInventario = env('MS_INVENTARIO_URL');
        $urlPagos = env('MS_PAGOS_URL');

        try {
            $token = $context->request()->bearerToken();

            // PASO 1: Transacción local (MS-Gestión)
            DB::beginTransaction();

            $venta = Venta::create([
                'id_usuario' => $args['id_usuario'],
                'total' => $args['total'],
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
                'estado' => 'PENDIENTE'
            ]);

            foreach ($args['detalles'] as $detalle) {
                DetalleVenta::create([
                    'id_venta' => $venta->id,
                    'id_unidad_rollo' => $detalle['id_unidad_rollo'],
                    'cantidad' => $detalle['cantidad'],
                    'precio' => $detalle['precio']
                ]);
            }
            DB::commit();

            // PASO 2: Reservar inventario (Spring Boot)
            $paso = 'Inventario';
            $reservaIntentada = true;
            $resReserva = Http::post("{$urlInventario}/reserva", [
                'idVenta' => $venta->id,
                'rollos'  => collect($args['detalles'])->map(fn ($d) => [
                    'idUnidadRollo' => $d['id_unidad_rollo'],
                    'cantidad'      => $d['cantidad'],
                ])->all(),
            ]);

            if (!$resReserva->successful()) {
                throw new \Exception($this->causa($resReserva));
            }

            // PASO 3: Realizar pago (NestJS)
            $paso = 'Pagos';
            $resPago = Http::withToken($token)->post("{$urlPagos}/procesar", [
                'id_venta' => $venta->id,
                'monto' => $args['total'],
                'moneda' => 'BOB',
                'idempotency_key' => $args['idempotencyKey']
            ]);

            if (!$resPago->successful()) {
                throw new \Exception($this->causa($resPago));
            }

            // PASO 4: Completar
            $venta->update(['estado' => 'COMPLETADO']);
            return $venta;

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // Mensaje limpio para el cliente
            if ($e instanceof ConnectionException) {
                $motivo = "No se pudo conectar con el servicio de {$paso}. Intente más tarde.";
            } else {
                $motivo = $e->getMessage();
            }
            $mensaje = "Venta cancelada. Falló el servicio de {$paso}: {$motivo}";

            // ACCIÓN COMPENSATORIA: liberar la reserva
            if ($reservaIntentada && $venta) {
                try {
                    Http::delete("{$urlInventario}/reserva/venta/" . $venta->id);
                } catch (\Exception $ex) {
                    // Si Spring también está caído, no ocultamos el error original
                }
            }

            if ($venta && $venta->exists) {
                $venta->update([
                    'estado' => 'CANCELADO',
                    'motivo_cancelacion' => mb_substr($mensaje, 0, 250)
                ]);
            }

            // GraphQL\Error\Error es "client safe": se muestra el mensaje tal cual
            throw new Error($mensaje);
        }
    }

    // Extrae solo la causa de la respuesta de un microservicio (sin el stack trace)
    private function causa(Response $res): string {
        $json = $res->json();
        $msg = null;

        if (is_array($json)) {
            $msg = $json['message'] ?? $json['error'] ?? null;
            if (is_array($msg)) {
                $msg = implode(', ', $msg); // NestJS puede devolver un array de mensajes
            }
        }

        return "HTTP {$res->status()}" . ($msg ? " - {$msg}" : '');
    }
}