<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../AdminInvoice/tests/bootstrap.php';
require_once __DIR__ . '/../src/Integration/Invoice/DirectDebitGatewayInterface.php';
require_once __DIR__ . '/../src/Integration/Invoice/InvoiceDirectDebit.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TaxRate;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\Model\InvoiceLine;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\AddressSnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PartySnapshot;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\Quantity;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\TaxCategory;
use Flexgrid\Modules\AdminInvoice\Service\InvoiceCalculator;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\DirectDebitGatewayInterface;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\InvoiceDirectDebit;

final class DebitTestGateway implements DirectDebitGatewayInterface
{
    public $payments = [];
    public $collections = [];
    public $back = [];
    public $mandateStatus = 'valid';
    public $mode = 'test';
    public $timeout = false;
    public $firstCount = 0;
    public function profile(): array { return ['id' => 'pfl_example', 'mode' => $this->mode]; }
    public function createCustomer(string $name, string $email, string $key): string { return 'cst_example'; }
    public function createFirst(string $customerId, string $webhookUrl, string $redirectUrl, string $key): array
    {
        $id = 'tr_first' . ++$this->firstCount;
        $this->payments[$id] = ['id' => $id, 'customer_id' => $customerId, 'mandate_id' => 'mdt_example',
            'profile_id' => 'pfl_example', 'mode' => $this->mode, 'sequence_type' => 'first', 'method' => 'ideal',
            'amount' => '0.01', 'currency' => 'EUR', 'status' => 'paid', 'paid_at' => '2026-10-06T12:00:00Z', 'metadata' => []];
        return ['id' => $id, 'url' => 'https://mollie.example/checkout/' . $id];
    }
    public function mandate(string $customerId, string $mandateId): array
    { return ['id' => $mandateId, 'method' => 'directdebit', 'status' => $this->mandateStatus]; }
    public function payment(string $paymentId): array { return $this->payments[$paymentId]; }
    public function collect(array $data, string $key): array
    {
        $this->collections[] = [$data, $key];
        $id = 'tr_debit' . count($this->collections);
        $this->payments[$id] = ['id' => $id, 'customer_id' => $data['customerId'], 'mandate_id' => $data['mandateId'],
            'profile_id' => 'pfl_example', 'mode' => $this->mode, 'sequence_type' => 'recurring', 'method' => 'directdebit',
            'amount' => $data['amount']['value'], 'currency' => $data['amount']['currency'], 'status' => 'paid',
            'paid_at' => '2026-10-20T12:00:00Z', 'metadata' => $data['metadata']];
        if ($this->timeout) { $this->timeout = false; throw new RuntimeException('Unknown remote outcome'); }
        return ['id' => $id];
    }
    public function chargebacks(string $paymentId): array { return $this->back; }
}

function debitTestInvoice(string $id, string $price = '10.00', bool $final = true): Invoice
{
    $currency = Currency::euro();
    $invoice = new Invoice($id, new TenantId('debit-tenant'), $currency,
        new PartySnapshot('Klant', '', 'klant@example.test', '', '', '', 'customer-one'),
        new AddressSnapshot('Straat 1', '1000 AA', 'Utrecht', 'NL'),
        new CalendarDate('2026-10-01'), new CalendarDate('2026-10-15'));
    $invoice->addLine(new InvoiceLine('line-' . $id, 'Maandpakket en klikken', Quantity::fromDecimal('1'), 'maand',
        Money::fromDecimal($price, $currency), TaxRate::fromPercentage('21'), new TaxCategory('standard'), 10));
    if ($final) { $invoice->finalize('2026-' . $id, 1, time(), (new InvoiceCalculator())->calculate($invoice)); }
    return $invoice;
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE admin_invoice_debit_customer (id INTEGER PRIMARY KEY, tenant_id TEXT, customer_public_id TEXT, provider_customer_id TEXT, mandate_id TEXT, setup_payment_id TEXT, setup_attempt INTEGER, setup_url TEXT, profile_id TEXT, mode TEXT, status TEXT, enabled INTEGER, consent_at INTEGER, error TEXT, created_at INTEGER, updated_at INTEGER, UNIQUE(tenant_id, customer_public_id))');
$pdo->exec('CREATE TABLE admin_invoice_direct_debit (id INTEGER PRIMARY KEY, tenant_id TEXT, invoice_public_id TEXT, invoice_number TEXT, customer_name TEXT, customer_public_id TEXT, provider_customer_id TEXT, mandate_id TEXT, profile_id TEXT, mode TEXT, payment_id TEXT UNIQUE, status TEXT, amount_minor INTEGER, currency TEXT, collect_on TEXT, notice TEXT, notice_sent_at INTEGER, error TEXT, created_at INTEGER, updated_at INTEGER, UNIQUE(tenant_id, invoice_public_id))');
$pdo->exec('CREATE TABLE admin_invoice_payment_link (tenant_id TEXT, invoice_public_id TEXT)');
$gateway = new DebitTestGateway();
$invoices = ['one' => debitTestInvoice('one'), 'two' => debitTestInvoice('two', '20.00'), 'three' => debitTestInvoice('three'),
    'four' => debitTestInvoice('four'), 'five' => debitTestInvoice('five'), 'six' => debitTestInvoice('six')];
$records = [];
$allocated = [];
$enabled = true;
$service = new InvoiceDirectDebit($pdo, new TenantId('debit-tenant'), $gateway,
    static function ($invoice, $amount, $currency, $external, $date, $type) use (&$records): void {
        $records[$external] = [$invoice, $amount, $currency, $date, $type];
    }, static function ($id) use (&$invoices) { return $invoices[$id]; },
    static function () use (&$enabled): bool { return $enabled; }, static function (): int { return 14; },
    'https://example.test/api/AdminInvoiceDirectDebit',
    static function ($id, $amount) use (&$allocated, &$invoices): void {
        if (!empty($allocated[$id]) || $invoices[$id]->getFinalizedTotals()->getGross()->getMinorUnits() !== $amount) {
            throw new DomainException('Outstanding changed');
        }
    });

adminPaymentAssert($service->prepare($invoices['one'], '2026-10-06') === '', 'Zonder klantmachtiging wordt geen incasso gepland.');
$setup = $service->createSetup('customer-one', 'Klant', 'klant@example.test', 'https://example.test/bedankt');
adminPaymentAssert($setup['status'] === 'link_ready' && (int)$setup['enabled'] === 0, 'Een link alleen geeft nog geen toestemming.');
adminPaymentAssertThrows(DomainException::class, static function () use ($service): void {
    $service->syncSetup('customer-one', 'tr_other');
}, 'Een vreemde setupbetaling wordt geweigerd.');
adminPaymentAssert($service->syncSetup('customer-one')['status'] === 'valid' && (int)$service->customer('customer-one')['enabled'] === 1,
    'Alleen bevestigde toestemming en een geldige SEPA-machtiging activeren incasso.');
adminPaymentAssertThrows(DomainException::class, static function () use ($service): void {
    $service->prepare(debitTestInvoice('draft', '10.00', false), '2026-10-06');
}, 'Concepten worden nooit geïncasseerd.');
$notice = $service->prepare($invoices['one'], '2026-10-06');
adminPaymentAssert(strpos($notice, '12,10') !== false && strpos($notice, '20-10-2026') !== false,
    'De aankondiging bevat het variabele factuurbedrag en minimaal de aankondigingstermijn.');
adminPaymentAssert($service->runDue('2026-10-30')['started'] === 0, 'Zonder verzonden aankondiging wordt niets geïncasseerd.');
$service->noticeSent('one');
adminPaymentAssert($service->runDue('2026-10-19')['started'] === 0, 'Incasso start niet vóór de aangekondigde datum.');
adminPaymentAssert($service->runDue('2026-10-20')['started'] === 1 && $gateway->collections[0][0]['amount']['value'] === '12.10',
    'Op de incassodatum wordt precies het goedgekeurde bedrag aangevraagd.');
adminPaymentAssert($service->runDue('2026-10-20')['started'] === 0 && count($gateway->collections) === 1, 'Herhaalde dagtaken starten geen dubbele incasso.');
$gateway->payments['tr_debit1']['amount'] = '12.11';
adminPaymentAssertThrows(DomainException::class, static function () use ($service): void { $service->synchronize('one'); }, 'Afwijkende bedragen worden niet geboekt.');
$gateway->payments['tr_debit1']['amount'] = '12.10';
$service->synchronize('one'); $service->synchronize('one');
adminPaymentAssert(count($records) === 1 && $records['tr_debit1'][1] === '12.10', 'Ontvangsten hebben een stabiele idempotentiesleutel.');
$gateway->back = [['id' => 'chb_one', 'amount' => '12.10', 'currency' => 'EUR', 'created_at' => '2026-10-22T12:00:00Z', 'reversed_at' => '']];
adminPaymentAssert($service->synchronize('one')['status'] === 'chargeback' && $records['chb_one'][1] === '-12.10', 'Terugboekingen verlagen de ontvangen betaling.');
$gateway->back[0]['reversed_at'] = '2026-10-23T12:00:00Z';
adminPaymentAssert($service->synchronize('one')['status'] === 'paid' && $records['chb_one:reversed'][1] === '12.10', 'Een teruggedraaide chargeback wordt apart gecorrigeerd.');
$gateway->back = [];
$service->prepare($invoices['two'], '2026-10-06'); $service->noticeSent('two');
$allocated['two'] = 100;
adminPaymentAssert($service->runDue('2026-10-20')['failed'] === 1 && count($gateway->collections) === 1, 'Een tussentijdse ontvangst blokkeert incasso van het oorspronkelijke bedrag.');
$service->prepare($invoices['three'], '2026-10-06'); $service->noticeSent('three');
$gateway->timeout = true;
adminPaymentAssert($service->runDue('2026-10-20')['failed'] === 1 && $service->findDebit('three')['status'] === 'attention', 'Een timeout blijft zichtbaar als onbekende uitkomst.');
adminPaymentAssert($service->runDue('2026-10-20')['started'] === 0 && count($gateway->collections) === 2, 'Onbekende uitkomsten worden niet opnieuw geïncasseerd.');
$gateway->payments['tr_debit2']['metadata']['invoice_public_id'] = 'foreign';
adminPaymentAssertThrows(DomainException::class, static function () use ($service): void { $service->recoverPayment('three', 'tr_debit2'); }, 'Herstel accepteert geen betaling van een andere factuur.');
$gateway->payments['tr_debit2']['metadata']['invoice_public_id'] = 'three';
adminPaymentAssert($service->recoverPayment('three', 'tr_debit2')['status'] === 'paid' && count($gateway->collections) === 2,
    'Herstel koppelt uitsluitend de bestaande, gecontroleerde betaling.');
$service->prepare($invoices['four'], '2026-10-06'); $service->noticeSent('four');
$service->setCustomerEnabled('customer-one', false);
adminPaymentAssert($service->runDue('2026-10-20')['stopped'] === 1, 'Pauzeren van de klant voorkomt nog niet gestarte incasso’s.');
$service->setCustomerEnabled('customer-one', true);
$enabled = false;
adminPaymentAssert($service->prepare($invoices['five'], '2026-10-06') === '', 'De globale incassoschakelaar staat los van goedkeuring en klanttoestemming.');
$enabled = true;
$pdo->exec("INSERT INTO admin_invoice_payment_link VALUES ('debit-tenant','five')");
adminPaymentAssertThrows(DomainException::class, static function () use ($service, $invoices): void { $service->prepare($invoices['five'], '2026-10-06'); }, 'Een bestaande betaallink voorkomt een tweede betaalroute.');
$gateway->mode = 'live';
adminPaymentAssertThrows(DomainException::class, static function () use ($service, $invoices): void { $service->prepare($invoices['six'], '2026-10-06'); }, 'Een testmachtiging wordt nooit met livegegevens gebruikt.');
$gateway->mode = 'test'; $gateway->mandateStatus = 'invalid';
adminPaymentAssertThrows(DomainException::class, static function () use ($service, $invoices): void { $service->prepare($invoices['six'], '2026-10-06'); }, 'Een ingetrokken machtiging stopt de volgende incasso.');
$gateway->mandateStatus = 'valid';
$renewed = $service->createSetup('customer-one', 'Klant', 'klant@example.test', 'https://example.test/bedankt');
adminPaymentAssert((int)$renewed['setup_attempt'] === 2 && (int)$renewed['enabled'] === 0 && $gateway->firstCount === 2,
    'Na een ingetrokken machtiging is nieuwe toestemming vereist.');
$foreign = new InvoiceDirectDebit($pdo, new TenantId('other-tenant'), $gateway, static function (): void {},
    static function ($id) use ($invoices) { return $invoices[$id]; }, static function (): bool { return true; },
    static function (): int { return 14; }, 'https://example.test/api/AdminInvoiceDirectDebit', static function (): void {});
adminPaymentAssert($foreign->findDebit('one') === null && $foreign->customer('customer-one') === null, 'Incasso en machtiging blijven tenantgebonden.');

echo "AdminPayment direct debit tests passed.\n";
