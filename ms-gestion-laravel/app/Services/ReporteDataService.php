<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Acceso a datos + armado de reportes para el agente.
 *  - Inventario y Pagos: HTTP (MS_INVENTARIO_URL / MS_PAGOS_URL)
 *  - Gestión (este Laravel): Eloquent
 * Todas las sumas, conteos y promedios se calculan AQUÍ, nunca en Gemini.
 */
class ReporteDataService
{
    private const MAX_MUESTRA       = 5;     // filas que ve Gemini al explorar
    private const MAX_FILAS_REPORTE = 2000;  // filas máximas en el reporte final
    private const MAX_TEXTO_MUESTRA = 80;    // recorta textos largos en la muestra
    private const TIPOS_COLUMNA     = ['texto', 'entero', 'numero', 'moneda', 'fecha', 'porcentaje'];

    // Campos que nunca deben salir del backend
    private const CAMPOS_SENSIBLES = [
        'passwords', 'stripe_customer_id', 'stripe_payment_method_id',
        'stripe_payment_intent_id', 'idempotency_key', 'stripe_error_code',
    ];

    private array $recursos;

    // Caché por petición para no repetir consultas a los microservicios
    private array $cache = [];

    public function __construct()
    {
        $inv = rtrim((string) env('MS_INVENTARIO_URL'), '/');
        $pag = rtrim((string) env('MS_PAGOS_URL'), '/');

        $this->recursos = [
            // ---- MS Inventario (Spring Boot) ----
            'categoria'       => ['tipo' => 'http', 'base' => $inv, 'path' => 'categoria', 'fecha' => null],
            'tela'            => ['tipo' => 'http', 'base' => $inv, 'path' => 'tela', 'fecha' => null],
            'proveedor'       => ['tipo' => 'http', 'base' => $inv, 'path' => 'proveedor', 'fecha' => null],
            'mercaderia'      => ['tipo' => 'http', 'base' => $inv, 'path' => 'mercaderia', 'fecha' => 'fecha'],
            'mercaderia_tela' => ['tipo' => 'http', 'base' => $inv, 'path' => 'mercaderia-tela', 'fecha' => null],
            'unidades_rollos' => ['tipo' => 'http', 'base' => $inv, 'path' => 'unidades-rollos', 'fecha' => null],

            // ---- MS Pagos (NestJS) ----
            'factura'         => ['tipo' => 'http', 'base' => $pag, 'path' => 'factura', 'fecha' => 'fecha_emision'],

            // ---- MS Gestión (Laravel, local) ----
            'usuario'         => ['tipo' => 'local', 'modelo' => \App\Models\Usuario::class, 'with' => [], 'fecha' => null],
            'venta'           => ['tipo' => 'local', 'modelo' => \App\Models\Venta::class, 'with' => ['detalles', 'usuario'], 'fecha' => 'fecha'],
            'detalle_venta'   => ['tipo' => 'local', 'modelo' => \App\Models\DetalleVenta::class, 'with' => [], 'fecha' => null],
        ];
    }

    public function nombresRecursos(): array
    {
        return array_keys($this->recursos);
    }

    // ==================================================================
    // HERRAMIENTAS DE EXPLORACIÓN (listar / obtener por id)
    // ==================================================================

    public function listar(string $recurso, ?int $anio = null, ?int $mes = null): array
    {
        $datos = $this->obtenerTodos($recurso, $anio, $mes);

        $estados = [];
        foreach ($datos as $fila) {
            if (isset($fila['estado']) && is_scalar($fila['estado'])) {
                $estados[(string) $fila['estado']] = true;
            }
        }

        return [
            'recurso'            => $recurso,
            'total'              => count($datos),
            'campos_disponibles' => $datos ? $this->rutasCampos($datos[0]) : [],
            'estados_distintos'  => array_map('strval', array_keys($estados)),
            'muestra'            => $this->recortar(array_slice($datos, 0, self::MAX_MUESTRA)),
        ];
    }

    public function obtenerPorId(string $recurso, int $id): array
    {
        $cfg = $this->config($recurso);

        if ($cfg['tipo'] === 'http') {
            $dato = $this->http($cfg, $cfg['path'] . '/' . $id);
            if ($dato === null) {
                return ['error' => "No existe $recurso con id $id."];
            }
        } else {
            $modelo = $cfg['modelo']::with($cfg['with'])->find($id);
            if (!$modelo) {
                return ['error' => "No existe $recurso con id $id."];
            }
            $dato = $modelo->toArray();
        }

        return ['recurso' => $recurso, 'dato' => $this->limpiar($dato)];
    }

    // ==================================================================
    // ARMADO DEL REPORTE (cálculos exactos en PHP)
    // ==================================================================

    public function armarReporte(array $spec): array
    {
        $recurso = (string) ($spec['recurso'] ?? '');
        $anio    = !empty($spec['anio']) ? (int) $spec['anio'] : null;
        $mes     = !empty($spec['mes']) ? (int) $spec['mes'] : null;

        if (empty($spec['columnas'])) {
            return ['error' => 'Debes indicar al menos una columna.'];
        }

        $todos     = $this->obtenerTodos($recurso, $anio, $mes);
        $filtrados = $this->aplicarFiltros($todos, $spec['filtros'] ?? []);

        // Orden
        if (!empty($spec['ordenar_por'])) {
            $campo = (string) $spec['ordenar_por'];
            $desc  = !empty($spec['orden_desc']);
            usort($filtrados, function ($a, $b) use ($campo, $desc) {
                $x = $this->valor($a, $campo);
                $y = $this->valor($b, $campo);
                $r = (is_numeric($x) && is_numeric($y))
                    ? ((float) $x <=> (float) $y)
                    : strcmp((string) $x, (string) $y);
                return $desc ? -$r : $r;
            });
        }

        // Columnas
        $columnas = [];
        foreach ($spec['columnas'] as $c) {
            $clave = (string) ($c['campo'] ?? '');
            if ($clave === '') {
                continue;
            }
            $tipo = in_array($c['tipo'] ?? '', self::TIPOS_COLUMNA, true) ? $c['tipo'] : 'texto';
            $columnas[] = [
                'clave'      => $clave,
                'titulo'     => (string) ($c['titulo'] ?? $clave),
                'tipo'       => $tipo,
                'alineacion' => in_array($tipo, ['entero', 'numero', 'moneda', 'porcentaje'], true) ? 'derecha' : 'izquierda',
            ];
        }
        if (empty($columnas)) {
            return ['error' => 'Las columnas no tienen un campo válido.'];
        }

        // Filas (valores tipados)
        $filas = [];
        foreach (array_slice($filtrados, 0, self::MAX_FILAS_REPORTE) as $registro) {
            $fila = [];
            foreach ($columnas as $col) {
                $fila[] = $this->castear($this->valor($registro, $col['clave']), $col['tipo']);
            }
            $filas[] = $fila;
        }

        // Resumen (los filtros del resumen se suman a los de la tabla)
        $resumen = [];
        foreach ($spec['resumen'] ?? [] as $r) {
            $resumen[] = $this->calcularResumen($filtrados, $r, $columnas); // CAMBIO: pasa $columnas
        }

        // Notas automáticas
        $notas = [];
        $excluidos = count($todos) - count($filtrados);
        if ($excluidos > 0) {
            $notas[] = "Se excluyeron $excluidos registros por los filtros aplicados.";
        }
        if (count($filtrados) > self::MAX_FILAS_REPORTE) {
            $notas[] = 'La tabla muestra las primeras ' . self::MAX_FILAS_REPORTE . ' filas de ' . count($filtrados) . '. Los totales del resumen incluyen todas.';
        }
        if (empty($filas)) {
            $notas[] = 'No se encontraron registros para los criterios indicados.';
        }

        return [
            'recurso'         => $recurso,
            'periodo'         => ['anio' => $anio, 'mes' => $mes],
            'total_origen'    => count($todos),
            'total_filtrado'  => count($filtrados),
            'columnas'        => $columnas,
            'filas'           => $filas,
            'resumen'         => $resumen,
            'notas'           => $notas,
        ];
    }

    /** CAMBIO: usa una columna numérica de respaldo si Gemini omitió o erró el 'campo'. */
    private function calcularResumen(array $registros, array $r, array $columnas = []): array
    {
        $registros = $this->aplicarFiltros($registros, $r['filtros'] ?? []);
        $op    = strtoupper((string) ($r['operacion'] ?? 'CONTEO'));
        $campo = (string) ($r['campo'] ?? '');

        // Primera columna moneda/numero de la tabla, como respaldo
        $respaldo = '';
        foreach ($columnas as $c) {
            if (in_array($c['tipo'], ['moneda', 'numero'], true)) {
                $respaldo = $c['clave'];
                break;
            }
        }

        $extraer = function (string $campo) use ($registros): array {
            $nums = [];
            foreach ($registros as $reg) {
                $x = $this->valor($reg, $campo);
                if (is_numeric($x)) {
                    $nums[] = (float) $x;
                }
            }
            return $nums;
        };

        if ($op === 'CONTEO' && $campo === '') {
            $valor = count($registros);
        } else {
            $nums = $campo !== '' ? $extraer($campo) : [];

            if (!$nums && $respaldo !== '' && $op !== 'CONTEO' && $registros) {
                $nums = $extraer($respaldo);
            }

            $valor = match ($op) {
                'SUMA'     => array_sum($nums),
                'PROMEDIO' => $nums ? array_sum($nums) / count($nums) : 0,
                'MAXIMO'   => $nums ? max($nums) : 0,
                'MINIMO'   => $nums ? min($nums) : 0,
                'CONTEO'   => count($nums),
                default    => 0,
            };
        }

        $tipo = in_array($r['tipo'] ?? '', self::TIPOS_COLUMNA, true)
            ? $r['tipo']
            : ($op === 'CONTEO' ? 'entero' : 'numero');

        return [
            'etiqueta' => (string) ($r['etiqueta'] ?? $op),
            'valor'    => $tipo === 'entero' ? (int) round($valor) : round((float) $valor, 2),
            'tipo'     => $tipo,
        ];
    }

    // ==================================================================
    // INTERNOS
    // ==================================================================

    private function config(string $recurso): array
    {
        if (!isset($this->recursos[$recurso])) {
            throw new Exception("Recurso desconocido: $recurso");
        }
        return $this->recursos[$recurso];
    }

    /** Todos los registros del recurso (con filtro opcional de año/mes), con caché por petición. */
    private function obtenerTodos(string $recurso, ?int $anio, ?int $mes): array
    {
        $clave = "$recurso|$anio|$mes";
        if (isset($this->cache[$clave])) {
            return $this->cache[$clave];
        }

        $cfg = $this->config($recurso);

        if ($cfg['tipo'] === 'http') {
            $datos = $this->http($cfg, $cfg['path']);
            if (!is_array($datos) || !array_is_list($datos)) {
                throw new Exception("Respuesta inválida del recurso '$recurso'.");
            }
            if ($cfg['fecha'] && ($anio || $mes)) {
                $datos = array_values(array_filter(
                    $datos,
                    fn($f) => $this->coincideFecha($f[$cfg['fecha']] ?? null, $anio, $mes)
                ));
            }
        } else {
            $query = $cfg['modelo']::with($cfg['with']);
            if ($cfg['fecha'] && $anio) {
                $query->whereYear($cfg['fecha'], $anio);
            }
            if ($cfg['fecha'] && $mes) {
                $query->whereMonth($cfg['fecha'], $mes);
            }
            $datos = $query->get()->toArray();
        }

        return $this->cache[$clave] = $this->limpiar($datos);
    }

    /** Devuelve el JSON, o null si el MS responde 404. */
    private function http(array $cfg, string $ruta): mixed
    {
        if ($cfg['base'] === '') {
            throw new Exception("Falta la URL del microservicio en el .env (MS_INVENTARIO_URL / MS_PAGOS_URL).");
        }

        $peticion = Http::connectTimeout(3)->timeout(10)->acceptJson();

        // Reenviar el JWT del usuario: el MS de Pagos (y otros) exigen autenticación
        $token = request()->bearerToken();
        if ($token) {
            $peticion = $peticion->withToken($token);
        }

        $t0 = microtime(true);
        try {
            $response = $peticion->get("{$cfg['base']}/{$ruta}");
        } catch (\Throwable $e) {
            Log::warning('Fallo al consultar microservicio', [
                'ruta'  => $ruta,
                'seg'   => round(microtime(true) - $t0, 2),
                'error' => $e->getMessage(),
            ]);
            throw new Exception("No se pudo conectar con el microservicio para '{$ruta}': " . mb_substr($e->getMessage(), 0, 150));
        }

        Log::info('Consulta a microservicio', [
            'ruta'   => $ruta,
            'status' => $response->status(),
            'seg'    => round(microtime(true) - $t0, 2),
        ]);

        if ($response->status() === 404) {
            return null;
        }
        if (!$response->successful()) {
            throw new Exception("Error al consultar '{$ruta}'. Código: " . $response->status());
        }
        return $response->json();
    }

    private function coincideFecha(mixed $valor, ?int $anio, ?int $mes): bool
    {
        if (!$valor) {
            return false;
        }
        $partes = explode('-', substr((string) $valor, 0, 10));
        if (count($partes) < 2) {
            return false;
        }
        if ($anio && (int) $partes[0] !== $anio) {
            return false;
        }
        if ($mes && (int) $partes[1] !== $mes) {
            return false;
        }
        return true;
    }

    /** Lee un campo con notación de puntos: "usuario.nombres". Devuelve null si falta o no es escalar. */
    private function valor(array $fila, string $ruta): mixed
    {
        $actual = $fila;
        foreach (explode('.', $ruta) as $clave) {
            if (!is_array($actual) || !array_key_exists($clave, $actual)) {
                return null;
            }
            $actual = $actual[$clave];
        }
        return is_array($actual) ? null : $actual;
    }

    private function castear(mixed $v, string $tipo): mixed
    {
        if ($v === null || $v === '') {
            return null;
        }
        return match ($tipo) {
            'entero'                         => is_numeric($v) ? (int) $v : null,
            'numero', 'moneda', 'porcentaje' => is_numeric($v) ? round((float) $v, 2) : null,
            'fecha'                          => substr((string) $v, 0, 10),
            default                          => is_scalar($v) ? (string) $v : null,
        };
    }

    private function aplicarFiltros(array $registros, array $filtros): array
    {
        if (empty($filtros)) {
            return $registros;
        }
        return array_values(array_filter($registros, function ($reg) use ($filtros) {
            foreach ($filtros as $f) {
                $v = $this->valor($reg, (string) ($f['campo'] ?? ''));
                if (!$this->compara($v, (string) ($f['operador'] ?? '='), (string) ($f['valor'] ?? ''))) {
                    return false;
                }
            }
            return true;
        }));
    }

    private function compara(mixed $v, string $op, string $ref): bool
    {
        if ($op === 'en') {
            $lista = array_map(fn($x) => mb_strtoupper(trim($x)), explode(',', $ref));
            return in_array(mb_strtoupper(trim((string) $v)), $lista, true);
        }
        if ($op === 'contiene') {
            return $v !== null && mb_stripos((string) $v, $ref) !== false;
        }
        if (is_numeric($v) && is_numeric($ref)) {
            $a = (float) $v;
            $b = (float) $ref;
            return match ($op) {
                '='  => $a == $b,
                '!=' => $a != $b,
                '>'  => $a > $b,
                '>=' => $a >= $b,
                '<'  => $a < $b,
                '<=' => $a <= $b,
                default => false,
            };
        }
        $a = mb_strtoupper(trim((string) $v));
        $b = mb_strtoupper(trim($ref));
        return match ($op) {
            '='  => $a === $b,
            '!=' => $a !== $b,
            default => false,
        };
    }

    /** Lista de rutas de campos escalares (ej. "tela.nombre") para que Gemini sepa qué columnas existen. */
    private function rutasCampos(array $fila, string $prefijo = '', int $nivel = 0): array
    {
        $rutas = [];
        foreach ($fila as $k => $v) {
            if (!is_array($v)) {
                $rutas[] = $prefijo . $k;
            } elseif ($nivel < 2 && $v !== [] && !array_is_list($v)) {
                $rutas = array_merge($rutas, $this->rutasCampos($v, $prefijo . $k . '.', $nivel + 1));
            }
        }
        return $rutas;
    }

    /** En la muestra, recorta textos largos y limita listas anidadas a 1 elemento. */
    private function recortar(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($v) && mb_strlen($v) > self::MAX_TEXTO_MUESTRA) {
                $data[$k] = mb_substr($v, 0, self::MAX_TEXTO_MUESTRA) . '…';
            } elseif (is_array($v)) {
                if ($v !== [] && array_is_list($v) && count($v) > 1 && is_array($v[0])) {
                    $v = array_slice($v, 0, 1);
                }
                $data[$k] = $this->recortar($v);
            }
        }
        return $data;
    }

    /** Quita campos sensibles de forma recursiva. */
    private function limpiar(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($k) && in_array($k, self::CAMPOS_SENSIBLES, true)) {
                unset($data[$k]);
            } elseif (is_array($v)) {
                $data[$k] = $this->limpiar($v);
            }
        }
        return $data;
    }
}