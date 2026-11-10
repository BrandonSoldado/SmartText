<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class GeminiAgentService
{
    private string $apiKey;
    private string $apiBase = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private string $urlInventario;

    // Modelos de respaldo en caso de saturación
    private array $modelos = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-flash-lite'];

    public function __construct()
    {
        $this->apiKey = trim((string) (config('services.gemini.key') ?: env('GEMINI_API_KEY')));
        $this->urlInventario = env('MS_INVENTARIO_URL');
    }

    public function procesarMensaje(string $textoCliente): array
    {
        if ($this->apiKey === '') {
            throw new Exception("Falta la API key de Gemini (GEMINI_API_KEY). Ejecuta php artisan config:clear.");
        }

        // 1. OBTENER EL CATÁLOGO DESDE EL MICROSERVICIO DE INVENTARIO (Spring Boot / NestJS)
        $catalogoTexto = $this->obtenerCatalogoDesdeInventario();

        // 2. System prompt con el catálogo dinámico
        $systemInstructionText = "Eres el Agente Comercial de la plataforma textil SmartTex. Atiendes clientes que piden telas en lenguaje natural.

CONTEXTO:
- Las telas se venden POR METRO. Los precios del catálogo son por metro.
- Tu única tarea es identificar qué tela(s) y cuántos METROS quiere el cliente.
- El backend elige los rollos, calcula precios y el total. Tú NO calculas precios ni totales y NO inventas IDs.

REGLAS:
1. Usa ÚNICAMENTE el CATÁLOGO DISPONIBLE obtenido del microservicio de inventario.
2. Si el cliente pide algo que no está en el catálogo, dile amablemente que no está disponible y sugiere alternativas parecidas.
3. Si no indica los metros (por ejemplo dice 'rollos' o no da cantidad), pregunta cuántos metros necesita. No asumas.
4. Si la tela es ambigua, pregunta cuál prefiere.
5. Si pide más metros que el stock de la tela, infórmalo y no ejecutes la compra.
6. Cuando el pedido sea claro y válido, ejecuta la herramienta 'registrar_venta' con id_tela y metros.
7. Responde siempre en español, de forma breve y amable.

CATÁLOGO DISPONIBLE:
" . $catalogoTexto;

        // 3. Payload con Function Calling (id_tela + metros)
        $payload = [
            "system_instruction" => [
                "parts" => [["text" => $systemInstructionText]]
            ],
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [["text" => $textoCliente]]
                ]
            ],
            "tools" => [
                [
                    "function_declarations" => [
                        [
                            "name" => "registrar_venta",
                            "description" => "Registra el pedido cuando el cliente indicó claramente la tela y los metros. Solo se envían id_tela y metros.",
                            "parameters" => [
                                "type" => "OBJECT",
                                "properties" => [
                                    "detalles" => [
                                        "type" => "ARRAY",
                                        "description" => "Lista de telas y metros a comprar.",
                                        "items" => [
                                            "type" => "OBJECT",
                                            "properties" => [
                                                "id_tela" => [
                                                    "type" => "INTEGER",
                                                    "description" => "ID de la tela según el catálogo."
                                                ],
                                                "metros" => [
                                                    "type" => "NUMBER",
                                                    "description" => "Metros solicitados."
                                                ]
                                            ],
                                            "required" => ["id_tela", "metros"]
                                        ]
                                    ]
                                ],
                                "required" => ["detalles"]
                            ]
                        ]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0
            ]
        ];

        // 4. Enviar a Gemini con reintentos y modelo de respaldo
        $response = null;

        foreach ($this->modelos as $modelo) {
            $url = "{$this->apiBase}{$modelo}:generateContent";

            $response = Http::withHeaders([
                    'x-goog-api-key' => $this->apiKey,
                    'Content-Type'   => 'application/json',
                ])
                ->timeout(30)
                ->post($url, $payload);

            if (in_array($response->status(), [429, 500, 503], true)) {
                sleep(1);
                $response = Http::withHeaders([
                        'x-goog-api-key' => $this->apiKey,
                        'Content-Type'   => 'application/json',
                    ])
                    ->timeout(30)
                    ->post($url, $payload);
            }

            if ($response->successful()) {
                break;
            }

            if (!in_array($response->status(), [404, 429, 500, 503], true)) {
                break;
            }
        }

        if (!$response || !$response->successful()) {
            throw new Exception("Error de Gemini: " . ($response ? $response->body() : 'sin respuesta'));
        }

        return $this->interpretarRespuesta($response->json());
    }

    /**
     * Consume el endpoint HTTP del microservicio de inventario para extraer el catálogo en tiempo real.
     */
    private function obtenerCatalogoDesdeInventario(): string
    {
        try {
            // Llama a http://localhost:8002/api/inventario/tela
            $response = Http::timeout(10)->get("{$this->urlInventario}/tela");

            if (!$response->successful()) {
                throw new Exception("No se pudo conectar con el MS de Inventario. Código: " . $response->status());
            }

            $telas = $response->json();

            if (empty($telas)) {
                return "No hay telas disponibles en este momento.";
            }

            $catalogoTexto = "";
            foreach ($telas as $t) {
                // Mapeamos los campos que devuelve tu API de inventario
                $id = $t['id'] ?? 0;
                $nombre = $t['nombre'] ?? 'Sin nombre';
                $color = $t['color'] ?? 'N/A';
                $composicion = $t['composicion'] ?? 'N/A';
                $precio = $t['precio'] ?? 0.00;
                $stock = $t['stock'] ?? 0;

                if ($stock > 0) {
                    $catalogoTexto .= sprintf(
                        "- id_tela: %d | %s | Color: %s | Composicion: %s | Precio: %.2f por metro | Stock: %s metros\n",
                        $id, $nombre, $color, $composicion, $precio, $stock
                    );
                }
            }

            return trim($catalogoTexto);

        } catch (Exception $e) {
            // Fallback por si el microservicio de inventario está caído
            throw new Exception("Error crítico en Agente al consultar Inventario: " . $e->getMessage());
        }
    }

    private function interpretarRespuesta(array $geminiData): array
    {
        $parts = $geminiData['candidates'][0]['content']['parts'] ?? [];
        $texto = '';

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                return [
                    'es_funcion'     => true,
                    'nombre_funcion' => $part['functionCall']['name'],
                    'argumentos'     => $part['functionCall']['args'] ?? [],
                ];
            }
            if (isset($part['text'])) {
                $texto .= $part['text'];
            }
        }

        return [
            'es_funcion' => false,
            'texto'      => trim($texto) !== '' ? trim($texto) : 'No entendí la solicitud.',
        ];
    }
}