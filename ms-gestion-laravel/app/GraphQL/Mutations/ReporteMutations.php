<?php

namespace App\GraphQL\Mutations;

use App\Services\GeminiReporteService;
use App\Services\ReporteExportService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ReporteMutations
{
    private GeminiReporteService $reporteService;
    private ReporteExportService $export;

    public function __construct(GeminiReporteService $reporteService, ReporteExportService $export)
    {
        $this->reporteService = $reporteService;
        $this->export         = $export;
    }

    /**
     * GraphQL: devuelve el reporte como objeto JSON. Si se pidió excel/pdf/word
     * (por el argumento "formato" o por el texto), agrega "archivo" con el binario en base64.
     */
    public function generarReporteNatural($_, array $args, GraphQLContext $context): array
    {
        $texto   = $args['texto'];
        $formato = $this->export->detectarFormato($texto, $args['formato'] ?? null);
        $reporte = $this->construir($texto);

        // JSON, o el agente respondió con mensaje/error (no hay tabla que exportar)
        if ($formato === 'json' || !isset($reporte['filas'])) {
            return $reporte;
        }

        try {
            $a = $this->export->generar($reporte, $formato);
            $reporte['archivo'] = [
                'nombre' => $a['nombre'],
                'mime'   => $a['mime'],
                'base64' => base64_encode($a['contenido']),
            ];
        } catch (\Throwable $e) {
            \Log::error('Error al exportar reporte', [
                'formato' => $formato,
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile() . ':' . $e->getLine(),
            ]);
            $reporte['error_exportacion'] = 'No se pudo generar el ' . $formato . ': ' . $e->getMessage();
        }

        return $reporte;
    }

    /**
     * Arma el reporte como array. Lo usan tanto GraphQL como el endpoint REST.
     */
    public function construir(string $texto): array
    {
        try {
            $respuesta = $this->reporteService->procesarSolicitudReporte($texto);

            // Pedido ambiguo o que no es un reporte
            if (!$respuesta['es_reporte']) {
                return ['mensaje' => $respuesta['texto']];
            }

            $r = $respuesta['reporte'];

            // JSON genérico: el frontend lo convierte a Excel, PDF o Word
            return [
                'version'   => '1.0',
                'metadatos' => [
                    'titulo'              => $r['titulo'],
                    'recurso'             => $r['recurso'],
                    'periodo'             => $r['periodo'],
                    'fecha_generacion'    => now()->setTimezone('America/La_Paz')->format('Y-m-d H:i:s'),
                    'total_registros'     => $r['total_filtrado'],
                    'registros_incluidos' => count($r['filas']),
                    'notas'               => $r['notas'],
                ],
                'columnas'    => $r['columnas'],
                'encabezados' => array_column($r['columnas'], 'titulo'),
                'filas'       => $r['filas'],
                'resumen'     => $r['resumen'],
            ];

        } catch (\Throwable $e) {
            \Log::error('Error en reporte natural', [
                'texto'   => $texto,
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return ['error' => 'Error al generar reporte: ' . $e->getMessage()];
        }
    }
}