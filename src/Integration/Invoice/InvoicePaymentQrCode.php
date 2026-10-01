<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/** Render the encoded Mollie URL as an inline PNG; no public image endpoint is needed. */
final class InvoicePaymentQrCode
{
    public function dataUri(string $url): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \LogicException('De PHP GD-extensie is nodig voor QR-codes op facturen.');
        }
        $matrix = Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix();
        $margin = 4;
        $modulePixels = 5;
        $size = ($matrix->getWidth() + 2 * $margin) * $modulePixels;
        $image = imagecreatetruecolor($size, $size);
        if ($image === false) {
            throw new \RuntimeException('QR-afbeelding kon niet worden aangemaakt.');
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);
        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                if ($matrix->get($x, $y) !== 1) { continue; }
                $left = ($x + $margin) * $modulePixels;
                $top = ($y + $margin) * $modulePixels;
                imagefilledrectangle($image, $left, $top, $left + $modulePixels - 1, $top + $modulePixels - 1, $black);
            }
        }
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        if (!is_string($png) || strncmp($png, "\x89PNG\r\n\x1a\n", 8) !== 0) {
            throw new \RuntimeException('QR-afbeelding kon niet worden opgeslagen.');
        }
        return 'data:image/png;base64,' . base64_encode($png);
    }
}
