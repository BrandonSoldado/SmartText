<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Exception;

class ReporteExportService
{
    private const MIMES = [
        'excel' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pdf'   => ['pdf',  'application/pdf'],
        'word'  => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    /** Formato explícito (si es válido) o detectado en el texto. Por defecto: json. */
    public function detectarFormato(string $texto, ?string $explicito = null): string
    {
        $f = mb_strtolower(trim((string) $explicito));
        if (in_array($f, ['json', 'excel', 'pdf', 'word'], true)) {
            return $f;
        }
        if (in_array($f, ['xlsx', 'xls'], true)) return 'excel';
        if (in_array($f, ['docx', 'doc'], true)) return 'word';

        $t = mb_strtolower($texto);
        if (preg_match('/\b(excel|xlsx|xls|hoja de c[aá]lculo|planilla)\b/u', $t)) return 'excel';
        if (preg_match('/\bpdf\b/u', $t)) return 'pdf';
        if (preg_match('/\b(word|docx)\b/u', $t)) return 'word';

        return 'json';
    }

    /** @return array{contenido:string, mime:string, nombre:string} */
    public function generar(array $reporte, string $formato): array
    {
        if (!isset(self::MIMES[$formato])) {
            throw new Exception("Formato no soportado: $formato");
        }

        [$ext, $mime] = self::MIMES[$formato];

        $contenido = match ($formato) {
            'excel' => $this->excel($reporte),
            'pdf'   => $this->pdf($reporte),
            'word'  => $this->word($reporte),
        };

        if ($contenido === '' || $contenido === false) {
            throw new Exception("El archivo $formato se generó vacío.");
        }

        $titulo = $reporte['metadatos']['titulo'] ?? 'reporte';
        $ascii  = @iconv('UTF-8', 'ASCII//TRANSLIT', $titulo) ?: $titulo;
        $slug   = trim(preg_replace('/[^a-z0-9]+/i', '_', $ascii), '_');

        return [
            'contenido' => $contenido,
            'mime'      => $mime,
            'nombre'    => ($slug ?: 'reporte') . '_' . date('Ymd_His') . '.' . $ext,
        ];
    }

    // ------------------------------------------------------------------
    // Excel
    // ------------------------------------------------------------------
    private function excel(array $r): string
    {
        if (!class_exists(Spreadsheet::class)) {
            throw new Exception('Falta phpoffice/phpspreadsheet (composer require phpoffice/phpspreadsheet).');
        }

        $meta = $r['metadatos'];
        $ss   = new Spreadsheet();
        $sh   = $ss->getActiveSheet();
        $sh->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $meta['titulo']) ?: 'Reporte', 0, 31));

        $sh->setCellValue('A1', $meta['titulo']);
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sh->setCellValue('A2', 'Generado: ' . $meta['fecha_generacion'] . '  |  Registros: ' . $meta['total_registros']);

        $filaEnc = 4;
        foreach ($r['columnas'] as $i => $col) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $sh->setCellValue($letra . $filaEnc, $col['titulo']);
        }
        $ultima = Coordinate::stringFromColumnIndex(count($r['columnas']));
        $sh->getStyle("A{$filaEnc}:{$ultima}{$filaEnc}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        ]);

        $fila = $filaEnc + 1;
        foreach ($r['filas'] as $datos) {
            foreach ($r['columnas'] as $i => $col) {
                $letra = Coordinate::stringFromColumnIndex($i + 1);
                $sh->setCellValue($letra . $fila, $datos[$i] ?? null);

                $fmt = match ($col['tipo']) {
                    'moneda', 'numero' => '#,##0.00',
                    'entero'           => '0',
                    'porcentaje'       => '0.00"%"',
                    default            => null,
                };
                if ($fmt) {
                    $sh->getStyle($letra . $fila)->getNumberFormat()->setFormatCode($fmt);
                }
                if ($col['alineacion'] === 'derecha') {
                    $sh->getStyle($letra . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
            $fila++;
        }

        // Resumen
        if (!empty($r['resumen'])) {
            $fila++;
            $sh->setCellValue("A{$fila}", 'Resumen');
            $sh->getStyle("A{$fila}")->getFont()->setBold(true);
            $fila++;
            foreach ($r['resumen'] as $item) {
                $sh->setCellValue("A{$fila}", $item['etiqueta']);
                $sh->setCellValue("B{$fila}", $item['valor']);
                $sh->getStyle("B{$fila}")->getFont()->setBold(true);
                if ($item['tipo'] !== 'entero') {
                    $sh->getStyle("B{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $fila++;
            }
        }

        foreach (range(1, count($r['columnas'])) as $i) {
            $sh->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $tmp = $this->archivoTemporal();
        try {
            (new Xlsx($ss))->save($tmp);
            return $this->leerYBorrar($tmp);
        } finally {
            $ss->disconnectWorksheets();
        }
    }

    // ------------------------------------------------------------------
    // PDF
    // ------------------------------------------------------------------
    private function pdf(array $r): string
    {
        if (!class_exists(Pdf::class)) {
            throw new Exception('Falta barryvdh/laravel-dompdf (composer require barryvdh/laravel-dompdf).');
        }

        $meta = $r['metadatos'];
        $e    = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $html  = '<html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#222}
            h1{font-size:15px;margin:0 0 4px}
            .meta{color:#666;margin-bottom:10px}
            table{width:100%;border-collapse:collapse}
            th{background:#1F4E78;color:#fff;padding:4px;text-align:left}
            td{border-bottom:1px solid #ddd;padding:3px 4px}
            .r{text-align:right}
            .resumen{margin-top:12px;width:auto}
            .resumen td{border:none;padding:2px 10px 2px 0;font-size:10px}
        </style></head><body>';
        $html .= '<h1>' . $e($meta['titulo']) . '</h1>';
        $html .= '<div class="meta">Generado: ' . $e($meta['fecha_generacion'])
              . ' &nbsp;|&nbsp; Registros: ' . $e($meta['total_registros']) . '</div>';

        $html .= '<table><thead><tr>';
        foreach ($r['columnas'] as $c) {
            $cls   = $c['alineacion'] === 'derecha' ? ' class="r"' : '';
            $html .= "<th$cls>" . $e($c['titulo']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($r['filas'] as $fila) {
            $html .= '<tr>';
            foreach ($r['columnas'] as $i => $c) {
                $cls   = $c['alineacion'] === 'derecha' ? ' class="r"' : '';
                $html .= "<td$cls>" . $e($this->formatear($fila[$i] ?? null, $c['tipo'])) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        if (!empty($r['resumen'])) {
            $html .= '<table class="resumen">';
            foreach ($r['resumen'] as $item) {
                $html .= '<tr><td>' . $e($item['etiqueta']) . '</td><td><b>'
                      . $e($this->formatear($item['valor'], $item['tipo'])) . '</b></td></tr>';
            }
            $html .= '</table>';
        }
        foreach ($meta['notas'] ?? [] as $nota) {
            $html .= '<p class="meta">' . $e($nota) . '</p>';
        }
        $html .= '</body></html>';

        $orientacion = count($r['columnas']) > 6 ? 'landscape' : 'portrait';

        return Pdf::loadHTML($html)->setPaper('a4', $orientacion)->output();
    }

    // ------------------------------------------------------------------
    // Word
    // ------------------------------------------------------------------
    private function word(array $r): string
    {
        if (!class_exists(PhpWord::class)) {
            throw new Exception('Falta phpoffice/phpword (composer require phpoffice/phpword).');
        }

        $meta = $r['metadatos'];
        $pw   = new PhpWord();
        $pw->setDefaultFontName('Calibri');
        $pw->setDefaultFontSize(10);

        $sec = $pw->addSection([
            'orientation' => count($r['columnas']) > 6 ? 'landscape' : 'portrait',
            'marginLeft'  => 700,
            'marginRight' => 700,
        ]);
        $sec->addText($meta['titulo'], ['bold' => true, 'size' => 16]);
        $sec->addText(
            'Generado: ' . $meta['fecha_generacion'] . '  |  Registros: ' . $meta['total_registros'],
            ['color' => '666666', 'size' => 9]
        );
        $sec->addTextBreak(1);

        $tabla = $sec->addTable(['borderSize' => 4, 'borderColor' => 'BBBBBB', 'cellMargin' => 50]);

        $fila = $tabla->addRow();
        foreach ($r['columnas'] as $c) {
            $fila->addCell(null, ['bgColor' => '1F4E78'])
                 ->addText($c['titulo'], ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
        }

        foreach ($r['filas'] as $datos) {
            $fila = $tabla->addRow();
            foreach ($r['columnas'] as $i => $c) {
                $fila->addCell()->addText(
                    $this->formatear($datos[$i] ?? null, $c['tipo']),
                    ['size' => 9],
                    ['alignment' => $c['alineacion'] === 'derecha' ? 'right' : 'left']
                );
            }
        }

        if (!empty($r['resumen'])) {
            $sec->addTextBreak(1);
            $sec->addText('Resumen', ['bold' => true, 'size' => 12]);
            foreach ($r['resumen'] as $item) {
                $linea = $sec->addTextRun();
                $linea->addText($item['etiqueta'] . ': ');
                $linea->addText($this->formatear($item['valor'], $item['tipo']), ['bold' => true]);
            }
        }
        foreach ($meta['notas'] ?? [] as $nota) {
            $sec->addText($nota, ['italic' => true, 'color' => '666666', 'size' => 9]);
        }

        $tmp = $this->archivoTemporal();
        IOFactory::createWriter($pw, 'Word2007')->save($tmp);
        return $this->leerYBorrar($tmp);
    }

    // ------------------------------------------------------------------
    private function archivoTemporal(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rep_');
        if ($tmp === false) {
            throw new Exception('No se pudo crear un archivo temporal.');
        }
        return $tmp;
    }

    private function leerYBorrar(string $ruta): string
    {
        $contenido = file_get_contents($ruta);
        @unlink($ruta);
        if ($contenido === false) {
            throw new Exception('No se pudo leer el archivo generado.');
        }
        return $contenido;
    }

    private function formatear(mixed $v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return match ($tipo) {
            'moneda', 'numero' => number_format((float) $v, 2, ',', '.'),
            'entero'           => number_format((float) $v, 0, ',', '.'),
            'porcentaje'       => number_format((float) $v, 2, ',', '.') . '%',
            default            => (string) $v,
        };
    }
}