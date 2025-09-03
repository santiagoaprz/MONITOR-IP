<?php
// generate_pdf.php
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/fpdf.php';

date_default_timezone_set('America/Mexico_City');

try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    file_put_contents($config['logs_dir'].'/error.log', date('c')." DB connect error: ".$e->getMessage()."\n", FILE_APPEND);
    exit(1);
}

// Obtener último estado por host
$sql = "SELECT h.id, h.label, h.host, h.type, h.last_status, MAX(c.checked_at) as last_check
        FROM hosts h
        LEFT JOIN checks c ON c.host_id = h.id
        GROUP BY h.id, h.host
        ORDER BY h.type, h.host";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// generar PDF
$pdf = new FPDF('P','mm','A4');
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,8,utf8_decode('REPORTE MONITOREO - ALCALDÍA TLALPAN'),0,1,'C');
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,utf8_decode('Generado: '.date('Y-m-d H:i:s')),0,1,'C');
$pdf->Ln(4);

// tabla por host
$pdf->SetFont('Arial','B',10);
$pdf->Cell(70,7,'Host',1,0,'C');
$pdf->Cell(25,7,'Tipo',1,0,'C');
$pdf->Cell(30,7,'Estado',1,0,'C');
$pdf->Cell(35,7,'Ult. chequeo',1,1,'C');

$pdf->SetFont('Arial','',9);
foreach ($rows as $r) {
    $estado = ($r['last_status'] === 'up') ? 'ARRIBA' : 'CAÍDO';
    $pdf->Cell(70,6,utf8_decode($r['label'].' ('.$r['host'].')'),1,0);
    $pdf->Cell(25,6,utf8_decode($r['type']),1,0,'C');
    $pdf->Cell(30,6,$estado,1,0,'C');
    $pdf->Cell(35,6,($r['last_check'] ?: '-'),1,1,'C');
}

$pdfFile = $config['reports_dir'] . '/Reporte_' . date('Ymd_His') . '.pdf';
$pdf->Output('F', $pdfFile);

echo "PDF generado: {$pdfFile}\n";
file_put_contents($config['logs_dir'] . '/reports.log', date('c').' - Generated '.$pdfFile."\n", FILE_APPEND);

