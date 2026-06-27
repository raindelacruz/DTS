<?php

class QrCodeService
{
    public static function generateSvgMarkup($text, $moduleSize = 4)
    {
        if (!class_exists('TCPDF2DBarcode')) {
            $barcodeFile = dirname(__DIR__, 2) . '/vendor/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
            if (is_file($barcodeFile)) {
                require_once $barcodeFile;
            }
        }

        if (!class_exists('TCPDF2DBarcode')) {
            throw new RuntimeException('QR/PDF dependencies are not installed. Run composer install.');
        }

        $barcode = new TCPDF2DBarcode((string) $text, 'QRCODE,M');
        return $barcode->getBarcodeSVGcode((int) $moduleSize, (int) $moduleSize, '#111827');
    }

    public static function generateSvgDataUri($text, $moduleSize = 4)
    {
        $svg = self::generateSvgMarkup($text, $moduleSize);
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}
