<?php
require __DIR__.'/fpdf.php';
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16); // título
$pdf->Cell(0,10,'Prueba PDF',0,1,'C');
$pdf->Output('F', __DIR__.'/reports/test.pdf');
echo "PDF generado correctamente.";
