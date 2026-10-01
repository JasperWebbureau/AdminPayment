<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../src/Integration/Invoice/InvoicePaymentQrCode.php';
require_once __DIR__ . '/../src/Integration/Invoice/MolliePaymentLinkGateway.php';

try {
    new \Flexgrid\Modules\AdminPayment\Integration\Invoice\MolliePaymentLinkGateway('test_example');
    throw new RuntimeException('Een Mollie-testkey mag nooit worden toegelaten voor facturen.');
} catch (LogicException $expected) {
    // Only live credentials may create invoice links.
}

$uri = (new \Flexgrid\Modules\AdminPayment\Integration\Invoice\InvoicePaymentQrCode())
    ->dataUri('https://www.mollie.com/payments/pl_example');
if (strpos($uri, 'data:image/png;base64,') !== 0) {
    throw new RuntimeException('Mollie QR-code moet als lokale PNG-data-URI beschikbaar zijn.');
}
$png = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
$size = is_string($png) ? getimagesizefromstring($png) : false;
if (!is_array($size) || $size[0] < 100 || $size[0] !== $size[1]) {
    throw new RuntimeException('Mollie QR-code moet een vierkante PNG met stille marge zijn.');
}
$invoice = [
    'document_type' => 'invoice', 'number' => '2026-0001', 'due_date' => '01-11-2026',
    'payment_url' => 'https://www.mollie.com/payments/pl_example', 'payment_qr_data_uri' => $uri,
];
$brand = [];
$company = [];
ob_start();
include __DIR__ . '/../../AdminInvoice/src/Templates/Pdf/default.php';
$html = (string)ob_get_clean();
if (strpos($html, 'data:image/png;base64,') === false || strpos($html, 'href="https://www.mollie.com/payments/pl_example"') === false) {
    throw new RuntimeException('De definitieve PDF moet de link en QR-code bevatten.');
}
$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();
if (strncmp($dompdf->output(), '%PDF-', 5) !== 0) {
    throw new RuntimeException('De PDF met QR-code moet lokaal kunnen renderen.');
}
$invoice['is_preview'] = true;
ob_start();
include __DIR__ . '/../../AdminInvoice/src/Templates/Pdf/default.php';
$previewHtml = (string)ob_get_clean();
if (strpos($previewHtml, 'href="https://www.mollie.com/payments/pl_example"') !== false) {
    throw new RuntimeException('Een concept-PDF mag geen betaal-URL bevatten.');
}
$invoice['is_preview'] = false;
$invoice['document_type'] = 'credit_note';
ob_start();
include __DIR__ . '/../../AdminInvoice/src/Templates/Pdf/default.php';
$creditHtml = (string)ob_get_clean();
if (strpos($creditHtml, 'href="https://www.mollie.com/payments/pl_example"') !== false) {
    throw new RuntimeException('Een creditnota mag geen betaal-URL bevatten.');
}

echo "AdminPayment QR tests passed.\n";
