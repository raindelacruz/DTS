<?php

require_once __DIR__ . '/fpdi/autoload.php';
require_once __DIR__ . '/tcpdf-full/tcpdf.php';

if (!class_exists('pdf', false) && class_exists('TCPDF', false)) {
    class_alias('TCPDF', 'pdf');
}

use setasign\Fpdi\Tcpdf\Fpdi;

class PdfOverlayService
{
    public static function streamStampedPdf($sourcePath, $verificationUrl, $downloadName)
    {
        // Temporarily Disabled – QR Code Printing Feature
        if (!defined('ENABLE_QR_PRINT') || ENABLE_QR_PRINT !== true) {
            header('Content-Type: application/pdf');
            header('Content-Length: ' . (string) filesize($sourcePath));
            header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($sourcePath);
            return;
        }

        $pdf = new Fpdi('P', 'mm');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCreator('DTS');
        $pdf->SetAuthor('DTS');
        $pdf->SetTitle('Stamped Attachment');

        $pageCount = $pdf->setSourceFile($sourcePath);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

            if ($pageNumber === 1) {
                self::drawQrOverlay($pdf, $verificationUrl, (float) $size['width'], (float) $size['height']);
            }
        }

        $pdf->Output($downloadName, 'I');
    }

    private static function drawQrOverlay(Fpdi $pdf, $verificationUrl, $pageWidth, $pageHeight)
    {
        $qrSize = min(28.0, $pageWidth * 0.14);
        $labelHeight = 8.0;
        $padding = 2.4;
        $panelWidth = $qrSize + ($padding * 2);
        $panelHeight = $qrSize + $labelHeight + ($padding * 2);

        $x = max(8.0, $pageWidth - $panelWidth - 16.0);
        $y = min(max($pageHeight * 0.26, 58.0), $pageHeight - $panelHeight - 12.0);

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(148, 163, 184);
        $pdf->SetLineWidth(0.25);
        $pdf->RoundedRect($x, $y, $panelWidth, $panelHeight, 2.4, '1111', 'DF');

        $pdf->write2DBarcode(
            $verificationUrl,
            'QRCODE,M',
            $x + $padding,
            $y + $padding,
            $qrSize,
            $qrSize,
            ['border' => false, 'padding' => 0, 'fgcolor' => [17, 24, 39], 'bgcolor' => false],
            'N'
        );

        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetXY($x + 1.2, $y + $padding + $qrSize + 0.8);
        $pdf->MultiCell($panelWidth - 2.4, 3.4, "SCAN TO VERIFY\nDOCUMENT", 0, 'C', false, 1, '', '', true, 0, false, true, 7.0, 'M');
    }
}
