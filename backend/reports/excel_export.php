<?php
// backend/reports/excel_export.php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['exportData'])) {
    die("Petición inválida.");
}

$data = json_decode($_POST['exportData'], true);

$reportTitle = $data['title'] ?? 'Reporte_SIGRAT';
$headers = $data['headers'] ?? [];
$rows = $data['rows'] ?? [];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr(preg_replace('/[^a-zA-Z0-9_\s]/', '', $reportTitle), 0, 31));

// Configurar ancho por defecto
$sheet->getDefaultColumnDimension()->setWidth(15);

// LOGO
$logoPath = __DIR__ . '/../../assets/img/logo.png';
if (file_exists($logoPath)) {
    $drawing = new Drawing();
    $drawing->setName('Logo SIGRAT');
    $drawing->setDescription('Logo SIGRAT');
    $drawing->setPath($logoPath);
    $drawing->setHeight(50);
    $drawing->setCoordinates('A1');
    $drawing->setOffsetX(10);
    $drawing->setOffsetY(10);
    $drawing->setWorksheet($sheet);
}

// Meta información
$sheet->setCellValue('B2', 'SISTEMA INTEGRAL DE GESTIÓN DE RECURSOS (SIGRAT)');
$sheet->getStyle('B2')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF1E3A8A');

$sheet->setCellValue('B3', strtoupper($reportTitle));
$sheet->getStyle('B3')->getFont()->setBold(true)->setSize(14);

$date = date('d/m/Y H:i');
session_start();
$user = isset($_SESSION['nombre']) ? $_SESSION['nombre'] . ' ' . ($_SESSION['apellido'] ?? '') : 'Administrador';

$sheet->setCellValue('B4', 'Fecha de generación: ' . $date);
$sheet->setCellValue('B5', 'Generado por: ' . $user);

// Mover fila de inicio
$startRow = 7;

// Escribir Headers
$col = 'A';
foreach ($headers as $headerText) {
    $sheet->setCellValue($col . $startRow, $headerText);
    $col++;
}
$lastCol = chr(ord('A') + count($headers) - 1); 

// Estilo de Headers
$headerRange = 'A' . $startRow . ':' . $lastCol . $startRow;
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['argb' => Color::COLOR_WHITE],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF1E3A8A']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FFFFFFFF'],
        ],
    ],
]);

// Auto-filtro y congelar la primera fila
$sheet->setAutoFilter($headerRange);
$sheet->freezePane('A' . ($startRow + 1));

// Escribir Filas
$currentRow = $startRow + 1;
foreach ($rows as $index => $row) {
    $col = 'A';
    foreach ($row as $cellValue) {
        $sheet->setCellValue($col . $currentRow, $cellValue);
        
        // Formato Condicional para palabras clave
        $cellValLower = strtolower(trim($cellValue));
        if (in_array($cellValLower, ['disponible', 'activo'])) {
            $sheet->getStyle($col . $currentRow)->getFont()->getColor()->setARGB('FF16A34A'); // Verde
            $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
        } elseif (in_array($cellValLower, ['prestado'])) {
            $sheet->getStyle($col . $currentRow)->getFont()->getColor()->setARGB('FFEA580C'); // Naranja
            $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
        } elseif (in_array($cellValLower, ['mantenimiento'])) {
            $sheet->getStyle($col . $currentRow)->getFont()->getColor()->setARGB('FF2563EB'); // Azul
            $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
        } elseif (in_array($cellValLower, ['extraviado', 'inactivo', 'baja'])) {
            $sheet->getStyle($col . $currentRow)->getFont()->getColor()->setARGB('FFDC2626'); // Rojo
            $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
        }
        $col++;
    }
    
    // Filas alternadas
    if ($index % 2 != 0) {
        $rowRange = 'A' . $currentRow . ':' . $lastCol . $currentRow;
        $sheet->getStyle($rowRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF1F5F9');
    }
    
    $currentRow++;
}

// Bordes para toda la tabla
$dataRange = 'A' . $startRow . ':' . $lastCol . ($currentRow - 1);
$sheet->getStyle($dataRange)->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FFCBD5E1'],
        ],
    ],
]);

// Autoajuste de columnas
for ($c = 'A'; $c <= $lastCol; $c++) {
    $sheet->getColumnDimension($c)->setAutoSize(true);
}

// Pie de página
$sheet->setCellValue('A' . ($currentRow + 1), 'Generado automáticamente por SIGRAT');
$sheet->getStyle('A' . ($currentRow + 1))->getFont()->setItalic(true)->getColor()->setARGB('FF94A3B8');
$sheet->mergeCells('A' . ($currentRow + 1) . ':' . $lastCol . ($currentRow + 1));

// Limpiar buffer
if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . preg_replace('/[^a-zA-Z0-9_\s]/', '', $reportTitle) . '_' . date('Ymd_Hi') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
