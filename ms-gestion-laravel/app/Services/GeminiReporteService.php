<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GeminiReporteService
{
    private string $apiKey;
    private string $apiBase = 'https://generativelanguage.googleapis.com/v1beta/models/';

    // Se pueden cambiar con GEMINI_MODELS en el .env (separados por coma)
    private array $modelos = ['gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-3.5-flash-lite'];

    private int $maxPasos = 6;

    // Presupuesto total de tiempo por solicitud (segundos) y tope por llamada a Gemini
    private const PRESUPUESTO_SEG = 100;
    private const TIMEOUT_LLAMADA = 30;

    private float $inicio;
    private ReporteDataService $datos;

    public function __construct(ReporteDataService $datos)
    {
        $this->datos  = $datos;
        $this->apiKey = trim((string) (config('services.gemini.key') ?: env('GEMINI_API_KEY')));

        $lista = trim((string) env('GEMINI_MODELS'));
        if ($lista !== '') {
            $this->modelos = array_values(array_filter(array_map('trim', explode(',', $lista))));
        }
    }

    /**
     * Devuelve:
     *  - ['es_reporte' => true,  'reporte' => [titulo, columnas, filas, resumen, notas, ...]]
     *  - ['es_reporte' => false, 'texto' => '...']
     */
    public function procesarSolicitudReporte(string $textoUsuario): array
    {
        if ($this->apiKey === '') {
            throw new Exception("Falta la API key de Gemini (GEMINI_API_KEY). Ejecuta php artisan config:clear.");
        }

        set_time_limit(self::PRESUPUESTO_SEG + 20);
        $this->inicio = microtime(true);

        $contents = [
            ['role' => 'user', 'parts' => [['text' => $textoUsuario]]],
        ];

        $vacios = 0;

        for ($paso = 0; $paso < $this->maxPasos; $paso++) {
            $data    = $this->llamarGemini($this->armarPayload($contents));
            $content = $data['candidates'][0]['content'] ?? null;
            $parts   = $content['parts'] ?? [];

            $llamadas = [];
            $texto    = '';
            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $llamadas[] = $part['functionCall'];
                } elseif (isset($part['text'])) {
                    $texto .= $part['text'];
                }
            }

            // Sin herramientas: respuesta de texto (aclaración o pedido que no es un reporte)
            if (empty($llamadas)) {
                if (trim($texto) === '') {
                    $motivo = $data['candidates'][0]['finishReason']
                        ?? ($data['promptFeedback']['blockReason'] ?? 'DESCONOCIDO');
                    Log::warning('Gemini devolvió una respuesta vacía', [
                        'paso'      => $paso,
                        'motivo'    => $motivo,
                        'respuesta' => $data,
                    ]);

                    if ($vacios < 2) {
                        $vacios++;
                        continue;
                    }
                    throw new Exception("Gemini no devolvió respuesta (motivo: $motivo). Revisa storage/logs/laravel.log.");
                }

                return ['es_reporte' => false, 'texto' => trim($texto)];
            }

            // Ejecutar herramientas
            $respuestas   = [];
            $reporteFinal = null;

            foreach ($llamadas as $llamada) {
                $args = $llamada['args'] ?? [];

                $t0        = microtime(true);
                $resultado = $this->ejecutarHerramienta($llamada['name'], $args);
                Log::info('Herramienta ejecutada', [
                    'herramienta' => $llamada['name'],
                    'recurso'     => $args['recurso'] ?? null,
                    'seg'         => round(microtime(true) - $t0, 2),
                    'error'       => $resultado['error'] ?? null,
                ]);

                if ($llamada['name'] === 'armar_reporte' && !isset($resultado['error'])) {
                    $resultado['titulo'] = (string) ($args['titulo'] ?? 'Reporte');
                    $reporteFinal = $resultado;
                }

                $respuestas[] = [
                    'functionResponse' => [
                        'name'     => $llamada['name'],
                        'response' => ['result' => $resultado],
                    ],
                ];
            }

            if ($reporteFinal !== null) {
                return ['es_reporte' => true, 'reporte' => $reporteFinal];
            }

            // Devolver resultados (o errores) a Gemini para que continúe o corrija
            $contents[] = $content;
            $contents[] = ['role' => 'user', 'parts' => $respuestas];
        }

        throw new Exception("El agente no pudo completar el reporte en {$this->maxPasos} pasos.");
    }

    private function ejecutarHerramienta(string $nombre, array $args): array
    {
        try {
            return match ($nombre) {
                'listar_recurso' => $this->datos->listar(
                    (string) ($args['recurso'] ?? ''),
                    !empty($args['anio']) ? (int) $args['anio'] : null,
                    !empty($args['mes']) ? (int) $args['mes'] : null
                ),
                'obtener_recurso_por_id' => $this->datos->obtenerPorId(
                    (string) ($args['recurso'] ?? ''),
                    (int) ($args['id'] ?? 0)
                ),
                'armar_reporte' => $this->datos->armarReporte($args),
                default => ['error' => "Herramienta desconocida: $nombre"],
            };
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Prompt y herramientas
    // ------------------------------------------------------------------

    private function armarPayload(array $contents): array
    {
        $recursos = $this->datos->nombresRecursos();

        $system = "Eres el Analista de Datos de la plataforma textil SmartTex. Generas reportes gerenciales a partir de solicitudes en lenguaje natural.

FECHA ACTUAL: " . date('Y-m-d') . " (mes " . (int) date('m') . ", año " . date('Y') . ").

HERRAMIENTAS:
- listar_recurso(recurso, anio?, mes?): EXPLORA un recurso. Devuelve total, campos_disponibles, estados_distintos y una muestra. Úsala UNA sola vez, sobre el recurso principal, antes de armar un reporte, para conocer los nombres exactos de los campos y los valores reales de 'estado'.
- obtener_recurso_por_id(recurso, id): trae un registro con sus relaciones.
- armar_reporte(...): construye el reporte FINAL. El backend filtra, ordena y calcula todas las sumas, conteos y promedios. Tú NO calculas nada ni copias filas: solo indicas qué columnas mostrar y qué cálculos hacer.

RECURSOS:
- venta (total, fecha, estado; relaciones usuario y detalles), detalle_venta, usuario.
- tela (nombre, color, precio, stock), unidades_rollos (cantidad_inicial/actual, estado_disponibilidad, con su tela), categoria.
- mercaderia (compras a proveedor, con proveedor), mercaderia_tela, proveedor.
- factura (nro_factura, razon_social, nit_ci, monto_total, estado, fecha_emision).

REGLAS:
1. Llama listar_recurso UNA vez y luego armar_reporte UNA vez. No explores recursos innecesarios.
2. Usa en 'campo' los nombres de campos_disponibles, con puntos para relaciones (ej. usuario.nombres, tela.nombre). Respeta mayúsculas y camelCase/snake_case tal como aparecen.
3. Mes mencionado → úsalo (1-12). Si el pedido no menciona periodo, NO pases anio ni mes (incluye todos los registros). Solo usa el mes actual si dice 'este mes'.
4. Si el usuario no pide filtrar por estado, la tabla incluye TODOS los registros. Para indicadores como 'ingresos efectivos', usa filtros dentro del resumen (los filtros del resumen se suman a los de la tabla).
5. Los estados pueden escribirse distinto para el mismo concepto (ej. COMPLETADA y COMPLETADO). Revisa estados_distintos y usa el operador 'en' con todas las variantes: valor 'COMPLETADA,COMPLETADO'.
6. Tipos de columna: entero (ids, cantidades enteras), numero (decimales), moneda (montos), fecha, porcentaje, texto.
7. Siempre agrega un resumen útil (conteo de registros y, si hay montos, suma). Para ventas o facturas incluye total general y total de las efectivamente completadas/emitidas. En SUMA, PROMEDIO, MAXIMO y MINIMO el 'campo' es OBLIGATORIO y debe ser un nombre exacto de campos_disponibles (ej. 'total', 'monto_total'). Solo se omite en CONTEO de filas.
8. Si hay un error o no hay datos, explícalo en texto. Nunca inventes datos.
9. Si el pedido no es un reporte o es ambiguo, responde en texto en español y pregunta lo necesario.
10. Responde siempre en español.";

        $filtroSchema = [
            'type'       => 'OBJECT',
            'properties' => [
                'campo'    => ['type' => 'STRING', 'description' => 'Campo, con puntos para relaciones.'],
                'operador' => ['type' => 'STRING', 'enum' => ['=', '!=', '>', '>=', '<', '<=', 'contiene', 'en'], 'description' => "'en' = lista separada por comas."],
                'valor'    => ['type' => 'STRING', 'description' => 'Valor a comparar.'],
            ],
            'required' => ['campo', 'operador', 'valor'],
        ];

        $tipos = ['texto', 'entero', 'numero', 'moneda', 'fecha', 'porcentaje'];

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents'           => $contents,
            'generationConfig'   => ['temperature' => 0.2],
            'tools'              => [['function_declarations' => [
                [
                    'name'        => 'listar_recurso',
                    'description' => 'Explora un recurso (GET /recurso): total, campos disponibles, estados distintos y una muestra de filas.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'recurso' => ['type' => 'STRING', 'enum' => $recursos],
                            'anio'    => ['type' => 'INTEGER', 'description' => 'Año a filtrar (opcional).'],
                            'mes'     => ['type' => 'INTEGER', 'description' => 'Mes 1-12 a filtrar (opcional).'],
                        ],
                        'required' => ['recurso'],
                    ],
                ],
                [
                    'name'        => 'obtener_recurso_por_id',
                    'description' => 'Obtiene un registro por su id (GET /recurso/{id}).',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'recurso' => ['type' => 'STRING', 'enum' => $recursos],
                            'id'      => ['type' => 'INTEGER'],
                        ],
                        'required' => ['recurso', 'id'],
                    ],
                ],
                [
                    'name'        => 'armar_reporte',
                    'description' => 'Construye el reporte final. El backend aplica filtros y calcula los totales.',
                    'parameters'  => [
                        'type'       => 'OBJECT',
                        'properties' => [
                            'titulo'      => ['type' => 'STRING', 'description' => 'Título del reporte, incluyendo el periodo si aplica.'],
                            'recurso'     => ['type' => 'STRING', 'enum' => $recursos],
                            'anio'        => ['type' => 'INTEGER'],
                            'mes'         => ['type' => 'INTEGER'],
                            'filtros'     => ['type' => 'ARRAY', 'items' => $filtroSchema, 'description' => 'Filtros de la tabla (opcional).'],
                            'ordenar_por' => ['type' => 'STRING', 'description' => 'Campo por el que ordenar (opcional).'],
                            'orden_desc'  => ['type' => 'BOOLEAN'],
                            'columnas'    => [
                                'type'  => 'ARRAY',
                                'items' => [
                                    'type'       => 'OBJECT',
                                    'properties' => [
                                        'campo'  => ['type' => 'STRING'],
                                        'titulo' => ['type' => 'STRING', 'description' => 'Encabezado visible.'],
                                        'tipo'   => ['type' => 'STRING', 'enum' => $tipos],
                                    ],
                                    'required' => ['campo', 'titulo', 'tipo'],
                                ],
                            ],
                            'resumen'     => [
                                'type'        => 'ARRAY',
                                'description' => 'Indicadores calculados por el backend.',
                                'items'       => [
                                    'type'       => 'OBJECT',
                                    'properties' => [
                                        'etiqueta'  => ['type' => 'STRING'],
                                        'operacion' => ['type' => 'STRING', 'enum' => ['SUMA', 'CONTEO', 'PROMEDIO', 'MAXIMO', 'MINIMO']],
                                        'campo'     => ['type' => 'STRING', 'description' => 'OBLIGATORIO en SUMA/PROMEDIO/MAXIMO/MINIMO (nombre exacto del campo numérico). Omitir solo en CONTEO de filas.'],
                                        'tipo'      => ['type' => 'STRING', 'enum' => $tipos],
                                        'filtros'   => ['type' => 'ARRAY', 'items' => $filtroSchema, 'description' => 'Filtros extra solo para este indicador.'],
                                    ],
                                    'required' => ['etiqueta', 'operacion'],
                                ],
                            ],
                        ],
                        'required' => ['titulo', 'recurso', 'columnas'],
                    ],
                ],
            ]]],
        ];

        // Opcional: reducir el "pensamiento" del modelo para responder más rápido.
        // Usa GEMINI_THINKING_BUDGET=0 en el .env. Si Gemini responde 400, quita esa variable.
        $budget = env('GEMINI_THINKING_BUDGET');
        if ($budget !== null && $budget !== '') {
            $payload['generationConfig']['thinkingConfig'] = ['thinkingBudget' => (int) $budget];
        }

        return $payload;
    }

    private function segundosRestantes(): float
    {
        return self::PRESUPUESTO_SEG - (microtime(true) - $this->inicio);
    }

    private function llamarGemini(array $payload): array
    {
        $errores = [];
        $detalle = '';

        foreach ($this->modelos as $modelo) {
            // Saltar modelos que ya dieron 404 hace poco
            if (Cache::has("gemini_modelo_invalido_$modelo")) {
                $errores[] = "$modelo → omitido (404 reciente)";
                continue;
            }

            $restante = $this->segundosRestantes();
            if ($restante < 5) {
                $errores[] = 'presupuesto de tiempo agotado';
                break;
            }

            $t0 = microtime(true);
            try {
                $response = Http::withHeaders([
                        'x-goog-api-key' => $this->apiKey,
                        'Content-Type'   => 'application/json',
                    ])
                    ->connectTimeout(5)
                    ->timeout((int) min(self::TIMEOUT_LLAMADA, $restante))
                    ->post("{$this->apiBase}{$modelo}:generateContent", $payload);
            } catch (\Throwable $e) {
                // Timeout o error de conexión: probar el siguiente modelo
                Log::warning('Gemini no respondió', [
                    'modelo' => $modelo,
                    'seg'    => round(microtime(true) - $t0, 2),
                    'error'  => $e->getMessage(),
                ]);
                $errores[] = "$modelo → " . mb_substr($e->getMessage(), 0, 120);
                continue;
            }

            Log::info('Gemini respondió', [
                'modelo' => $modelo,
                'status' => $response->status(),
                'seg'    => round(microtime(true) - $t0, 2),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            $detalle   = mb_substr($response->body(), 0, 300);
            $errores[] = "$modelo → HTTP " . $response->status();

            if ($response->status() === 404) {
                Cache::put("gemini_modelo_invalido_$modelo", true, 600);
            }

            // Errores que no se arreglan cambiando de modelo (400, 401, 403)
            if (!in_array($response->status(), [404, 429, 500, 503], true)) {
                break;
            }
        }

        throw new Exception(
            "Error de Gemini. " . implode(' | ', $errores) . ($detalle ? ". Detalle: $detalle" : '')
        );
    }
}