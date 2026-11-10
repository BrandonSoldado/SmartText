<?php

namespace App\GraphQL\Mutations;

use App\Services\GeminiAgentService;
use App\Services\SagaPedidoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use GraphQL\Error\Error;

class AgenteMutations
{
    private GeminiAgentService $geminiService;
    private SagaPedidoService $sagaService;

    public function __construct(GeminiAgentService $geminiService, SagaPedidoService$sagaService)
    {
        $this->geminiService =$geminiService;
        $this->sagaService   =$sagaService;
    }

    public function procesarCompraNatural($_, array $args, GraphQLContext$context)
    {
        $textoUsuario =$args['texto'];

        // Siempre el usuario del token, nunca un id enviado por el cliente
        $idUsuario =$context->user()->id;
        // Extraemos el Token JWT para pasárselo a NestJS en la Saga
        $tokenJwt =$context->request()->bearerToken();

        try {
            // 1. Enviamos el texto a Gemini
            $respuestaAgente = $this->geminiService->procesarMensaje($textoUsuario);

            // 2. Si no es compra, devolvemos el texto de Gemini
            if (!$respuestaAgente['es_funcion']) {
                return $respuestaAgente['texto'];
            }

            // 3. Es compra: el backend asigna rollos y calcula precios/total
            $argumentos = $respuestaAgente['argumentos'];$detallesInput = $this->armarDetalles($argumentos['detalles'] ?? []);

            // 4. Ejecutamos la Saga (Agregamos idempotencyKey y el Token)
            $idempotencyKey = (string) Str::uuid();
            $venta =$this->sagaService->ejecutarSaga($idUsuario,$detallesInput, $idempotencyKey,$tokenJwt);

            $total = collect($detallesInput)->sum(fn ($d) => $d['cantidad'] *$d['precio']);

            return "Listo, he procesado tu compra. Pedido #" . $venta->id
                . ". Total: " . number_format($total, 2) . ".";

        } catch (\Exception $e) {
            throw new Error("El agente encontro un error: " . $e->getMessage());
        }
    }

    /**
     * Convierte [{id_tela, metros}] en [{id_unidad_rollo, cantidad, precio}]
     * usando precios y stock reales de la base de datos.
     */
    private function armarDetalles(array $pedidos): array
    {
        if (empty($pedidos)) {
            throw new \Exception("No se indico ningun producto.");
        }

        $detalles = [];

        foreach ($pedidos as$p) {
            $idTela = (int) ($p['id_tela'] ?? 0);
            $metros = (float) ($p['metros'] ?? 0);

            if ($idTela <= 0 || $metros <= 0) {
                throw new \Exception("Pedido invalido: tela o metros incorrectos.");
            }

            $tela = DB::table('tela')->where('id',$idTela)->first();
            if (!$tela) {
                throw new \Exception("La tela solicitada no existe.");
            }

            // Rollos disponibles, primero los de menor metraje para vaciar retazos
            $rollos = DB::table('unidades_rollos')
                ->where('id_tela', $idTela)
                ->where('estado_disponibilidad', 'DISPONIBLE')
                ->where('cantidad_actual', '>', 0)
                ->orderBy('cantidad_actual', 'asc')
                ->get();

            $falta =$metros;

            foreach ($rollos as$rollo) {
                if ($falta <= 0) {
                    break;
                }

                $tomar = min($falta, (float)$rollo->cantidad_actual);

                $detalles[] = [
                    'id_unidad_rollo' => (int) $rollo->id,
                    'cantidad'        => round($tomar, 2), 'precio'          => (float)$tela->precio, // precio real de la BD
                ];

                // CORRECCIÓN APLICADA: Se agregó el guión de resta (-)
                $falta = round($falta -$tomar, 2);
            }

            if ($falta > 0) {
                throw new \Exception("Stock insuficiente de {$tela->nombre}. No hay {$metros} metros disponibles.");
            }
        }

        return $detalles;
    }
}