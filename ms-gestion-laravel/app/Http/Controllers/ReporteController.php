<?php

namespace App\Http\Controllers;

use App\GraphQL\Mutations\ReporteMutations;
use App\Services\ReporteExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReporteController
{
    public function generar(Request $request, ReporteMutations $reportes, ReporteExportService $export)
    {
        $request->validate([
            'texto'   => 'required|string',
            'formato' => 'nullable|string',
        ]);

        $texto   = $request->input('texto');
        $formato = $export->detectarFormato($texto, $request->input('formato'));
        $reporte = $reportes->construir($texto);

        Log::info('Export', [
            'formato_recibido' => $request->input('formato'),
            'formato_detectado' => $formato,
            'tiene_filas'       => isset($reporte['filas']),
            'claves_reporte'    => array_keys($reporte),
        ]);

        // JSON, o el agente respondió con mensaje/error (no hay tabla que exportar)
        if ($formato === 'json' || !isset($reporte['filas'])) {
            return response()->json($reporte)
                ->header('X-Formato-Solicitado', (string) $request->input('formato'))
                ->header('X-Formato-Generado', 'json');
        }

        try {
            $archivo = $export->generar($reporte, $formato);
        } catch (\Throwable $e) {
            Log::error('Error al exportar reporte', [
                'formato' => $formato,
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'error' => 'No se pudo generar el ' . $formato . ': ' . $e->getMessage(),
            ], 500);
        }

        // Evita que cualquier texto suelto corrompa el binario
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response($archivo['contenido'], 200, [
            'Content-Type'                  => $archivo['mime'],
            'Content-Disposition'           => 'attachment; filename="' . $archivo['nombre'] . '"',
            'Content-Length'                => strlen($archivo['contenido']),
            'X-Formato-Solicitado'          => (string) $request->input('formato'),
            'X-Formato-Generado'            => $formato,
            'Access-Control-Expose-Headers' => 'Content-Disposition, X-Formato-Generado',
        ]);
    }
}