<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\InvoiceMollieLinks;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\PaymentLinkGatewayInterface;

final class PaymentLinkTestGateway implements PaymentLinkGatewayInterface
{
    public $belongs = true;
    public $amount = '12.10';
    public $status = 'paid';
    public $creates = [];
    public function create(string $description, string $currency, string $amount, string $webhookUrl): array
    {
        $this->creates[] = [$description, $currency, $amount, $webhookUrl];
        return ['id' => 'pl_test123', 'url' => 'https://www.mollie.com/payments/test123', 'mode' => 'test'];
    }
    public function payment(string $paymentId, string $linkId): array
    {
        return [
            'id' => $paymentId, 'status' => $this->status,
            'belongs_to_link' => $this->belongs && $linkId === 'pl_test123',
            'currency' => 'EUR', 'amount' => $this->amount,
            'paid_at' => '2026-09-28T12:00:00+00:00',
        ];
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE admin_invoice_payment_link (id INTEGER PRIMARY KEY, tenant_id TEXT, invoice_public_id TEXT, status TEXT, provider_link_id TEXT, url TEXT, amount_minor INTEGER, currency TEXT, payment_id TEXT, error TEXT, created_at INTEGER, updated_at INTEGER, UNIQUE(tenant_id, invoice_public_id), UNIQUE(provider_link_id))');
$gateway = new PaymentLinkTestGateway();
$recorded = [];
$links = new InvoiceMollieLinks($pdo, new TenantId('one'), $gateway,
    static function (string $invoiceId, string $amount, string $currency, string $paymentId, string $bookedOn) use (&$recorded): void {
        $recorded[] = [$invoiceId, $amount, $currency, $paymentId, $bookedOn];
    });
$invoice = new InvoiceDetailContext('invoice-1', '2026-0001', 'final', 'unpaid',
    Money::fromDecimal('12.10', Currency::euro()));
$link = $links->create($invoice, 'https://example.test/api/AdminInvoicePayment/webhook');
adminPaymentAssert($link['status'] === 'ready' && $gateway->creates[0][2] === '12.10'
    && $gateway->creates[0][3] === 'https://example.test/api/AdminInvoicePayment/webhook/invoice-1',
    'Mollie-link moet het exacte factuurbedrag en de factuurgebonden webhook krijgen.');
adminPaymentAssertThrows(DomainException::class, static function () use ($links, $invoice): void {
    $links->create($invoice, 'https://example.test/api/AdminInvoicePayment/webhook');
}, 'Per factuur mag niet per ongeluk een tweede betaallink ontstaan.');
$gateway->belongs = false;
adminPaymentAssertThrows(DomainException::class, static function () use ($links): void {
    $links->synchronize('invoice-1', 'tr_payment1');
}, 'Een betaling van een andere Mollie-link mag niet worden geboekt.');
$gateway->belongs = true;
$gateway->amount = '13.10';
adminPaymentAssertThrows(DomainException::class, static function () use ($links): void {
    $links->synchronize('invoice-1', 'tr_payment1');
}, 'Een betaling met afwijkend bedrag mag niet worden geboekt.');
$gateway->amount = '12.10';
adminPaymentAssert($links->synchronize('invoice-1', 'tr_payment1') === true
    && $recorded === [['invoice-1', '12.10', 'EUR', 'tr_payment1', '2026-09-28']],
    'Bevestigde Mollie-betaling moet exact eenmaal aan AdminPayment worden doorgegeven.');
adminPaymentAssert($links->synchronize('invoice-1', 'tr_payment1') === true && count($recorded) === 1,
    'Herhaalde Mollie-webhooks moeten idempotent blijven.');

echo "AdminPayment Mollie link tests passed.\n";
