<?php

require_once __DIR__ . '/tcpdf/tcpdf_barcodes_2d.php';

class QrCodeService
{
    public static function generateSvgMarkup($text, $moduleSize = 4)
    {
        $barcode = new TCPDF2DBarcode((string) $text, 'QRCODE,M');
        return $barcode->getBarcodeSVGcode((int) $moduleSize, (int) $moduleSize, '#111827');
    }

    public static function generateSvgDataUri($text, $moduleSize = 4)
    {
        $svg = self::generateSvgMarkup($text, $moduleSize);
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}
