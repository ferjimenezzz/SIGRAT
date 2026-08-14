<?php
// Cargar autoloader de vendor (soporta vendor en raíz del proyecto y vendor en backend)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['filters'])) {
    die("Petición inválida.");
}

// Aumentar límites para exportaciones grandes (sin límite de filas)
set_time_limit(0);
ini_set('memory_limit', '512M');

// Decodificar filtros enviados desde el frontend
$filters = json_decode($_POST['filters'], true);

// Reutilizar la conexión Singleton del proyecto (PostgreSQL / Supabase)
require_once __DIR__ . '/../config/Database.php';
$pdo = Config\Database::getConnection();

// Construir la consulta SQL basada en los filtros usando el helper reutilizable
require_once __DIR__ . '/../helpers/InventoryQueryBuilder.php';
$params = [];
$sql = InventoryQueryBuilder::build($filters, $params);

// Ejecutar la consulta y obtener todos los registros
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determinar encabezados a partir de los datos obtenidos
$reportTitle = $filters['title'] ?? 'Reporte_SIGRAT';
$headers = $rows ? array_keys($rows[0]) : [];

// Crear el Spreadsheet
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

// Identificar columnas FOTO
$fotoCols = [];
$col = 'A';
foreach ($headers as $headerText) {
    if (strtolower(trim($headerText)) === 'foto') {
        $fotoCols[] = $col;
    }
    $col++;
}

// Escribir Filas
$currentRow = $startRow + 1;
foreach ($rows as $index => $row) {
    $col = 'A';
    foreach ($row as $cellValue) {
        if (in_array($col, $fotoCols) && !empty($cellValue) && (filter_var($cellValue, FILTER_VALIDATE_URL) || strpos($cellValue, 'cloudinary') !== false)) {
            // Es una URL de imagen válida
            $sheet->setCellValue($col . $currentRow, $cellValue);
            $sheet->getCell($col . $currentRow)->getHyperlink()->setUrl($cellValue);
            $sheet->getStyle($col . $currentRow)->getFont()->setUnderline(true)->getColor()->setARGB('FF2563EB'); // Link azul
            
            // Intentar descargar e insertar la imagen en la celda
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'follow_location' => true
                ]
            ]);
            $imgData = @file_get_contents($cellValue, false, $ctx);
            if ($imgData !== false) {
                $tempFile = tempnam(sys_get_temp_dir(), 'excel_img_');
                file_put_contents($tempFile, $imgData);
                
                $imageSize = @getimagesize($tempFile);
                if ($imageSize !== false) {
                    $drawing = new Drawing();
                    $drawing->setName('Foto');
                    $drawing->setDescription('Foto del activo');
                    $drawing->setPath($tempFile);
                    
                    // Ajustar alto de la fila para la imagen
                    $sheet->getRowDimension($currentRow)->setRowHeight(55);
                    
                    $drawing->setHeight(50);
                    $drawing->setCoordinates($col . $currentRow);
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($sheet);
                    
                    register_shutdown_function(function() use ($tempFile) {
                        @unlink($tempFile);
                    });
                } else {
                    @unlink($tempFile);
                }
            }
        } else {
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

// Autoajuste de columnas (evitando la de la foto por contener urls largas)
for ($c = 'A'; $c <= $lastCol; $c++) {
    if (in_array($c, $fotoCols)) {
        $sheet->getColumnDimension($c)->setWidth(18);
    } else {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
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
