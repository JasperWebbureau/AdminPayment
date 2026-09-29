<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\PayableInvoice;
use Flexgrid\Modules\AdminInvoice\Contract\InvoicePaymentPortInterface;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminPayment\Application\Command\RegisterInvoicePaymentCommand;
use Flexgrid\Modules\AdminPayment\Contract\PaymentRepositoryInterface;
use Flexgrid\Modules\AdminPayment\Domain\Model\Payment;
use Flexgrid\Modules\AdminPayment\Exception\DuplicatePaymentReferenceException;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\RegisterInvoicePayment;
use Flexgrid\Utils\_Time;

final class PaymentTestTransactions implements TransactionManagerInterface
{
    /** @var bool */ public $active = false;
    /** @var string[] */ public $trace = [];
    public function transactional(callable $operation)
    {
        $this->active = true;
        $this->trace[] = 'begin';
        try {
            $result = $operation();
            $this->trace[] = 'commit';
            return $result;
        } catch (Throwable $throwable) {
            $this->trace[] = 'rollback';
            throw $throwable;
        } finally {
            $this->active = false;
        }
    }
}

final class PaymentTestIds implements PublicIdGeneratorInterface
{
    /** @var int */ private $next = 1;
    public function generate(): string { return 'payment-test-' . $this->next++; }
}

final class MemoryPayments implements PaymentRepositoryInterface
{
    /** @var PaymentTestTransactions */ private $transactions;
    /** @var Payment[] */ public $payments = [];
    public function __construct(PaymentTestTransactions $transactions) { $this->transactions = $transactions; }
    public function insert(Payment $payment, int $createdAt): void
    {
        adminPaymentAssert($this->transactions->active, 'Payment moet binnen de transactie worden opgeslagen.');
        $this->payments[] = $payment;
    }
    public function findByExternalReference(TenantId $tenantId, string $source, string $externalId): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->getTenantId()->equals($tenantId)
                && $payment->getSource() === $source
                && $payment->getExternalId() === $externalId
            ) {
                return $payment;
            }
        }
        return null;
    }
    public function getAllocatedTotal(TenantId $tenantId, string $targetType, string $targetPublicId, Currency $currency): Money
    {
        adminPaymentAssert($this->transactions->active, 'Afgeletterd totaal moet binnen dezelfde transactie worden gelezen.');
        $total = Money::zero($currency);
        foreach ($this->payments as $payment) {
            if (!$payment->getTenantId()->equals($tenantId)) {
                continue;
            }
            foreach ($payment->getAllocations() as $allocation) {
                if ($allocation->getTargetType() === $targetType && $allocation->getTargetPublicId() === $targetPublicId) {
                    $total = $total->add($allocation->getAmount());
                }
            }
        }
        return $total;
    }
    public function listAllocationsForTarget(TenantId $tenantId, string $targetType, string $targetPublicId, Currency $currency): array
    {
        return [];
    }
}

final class MemoryInvoicePaymentPort implements InvoicePaymentPortInterface
{
    /** @var PaymentTestTransactions */ private $transactions;
    /** @var PayableInvoice */ public $invoice;
    /** @var string[] */ public $statuses = [];
    public function __construct(PaymentTestTransactions $transactions, PayableInvoice $invoice)
    {
        $this->transactions = $transactions;
        $this->invoice = $invoice;
    }
    public function findPayableByPublicIdForUpdate(TenantId $tenantId, string $publicId): ?PayableInvoice
    {
        adminPaymentAssert($this->transactions->active, 'Factuur moet binnen de transactie worden vergrendeld.');
        return $this->invoice->getTenantId()->equals($tenantId) && $this->invoice->getPublicId() === $publicId
            ? $this->invoice
            : null;
    }
    public function synchronizePaymentStatus(PayableInvoice $invoice, Money $allocatedTotal, int $updatedAt): string
    {
        adminPaymentAssert($this->transactions->active, 'Betaalstatus moet binnen dezelfde transactie worden bijgewerkt.');
        $gross = $invoice->getGrossTotal()->getMinorUnits();
        $allocated = $allocatedTotal->getMinorUnits();
        $status = $allocated <= 0 ? 'unpaid' : ($allocated < $gross ? 'partially_paid' : ($allocated === $gross ? 'paid' : 'overpaid'));
        $this->statuses[] = $status;
        return $status;
    }
}

$tenantId = new TenantId('payment-integration-tenant');
$currency = Currency::euro();
$transactions = new PaymentTestTransactions();
$payments = new MemoryPayments($transactions);
$invoicePort = new MemoryInvoicePaymentPort($transactions, new PayableInvoice(
    $tenantId,
    'invoice-1',
    '2026-0001',
    InvoiceStatus::FINALIZED,
    new Money(12100, $currency)
));
$useCase = new RegisterInvoicePayment(
    new TenantContext($tenantId),
    new PaymentTestIds(),
    $transactions,
    $payments,
    $invoicePort,
    new _Time(strtotime('2026-09-18 12:00:00 UTC'))
);

$first = $useCase->execute(new RegisterInvoicePaymentCommand('invoice-1', '40.00', 'EUR', '2026-09-18', 'receipt', '', 'BANK-1', 'bank_import', 'ext-1'));
adminPaymentAssert($first->isFullyAllocated(), 'Eerste slice moet iedere betaling exact op één factuur alloceren.');
adminPaymentAssert($invoicePort->statuses === ['partially_paid'], 'Eerste gedeeltelijke betaling moet status gedeeltelijk betaald projecteren.');

$useCase->execute(new RegisterInvoicePaymentCommand('invoice-1', '81.00', 'EUR', '2026-09-19', 'receipt', '', 'BANK-2', 'bank_import', 'ext-2'));
adminPaymentAssert($invoicePort->statuses[1] === 'paid', 'Meerdere betalingen moeten samen betaald opleveren.');

$useCase->execute(new RegisterInvoicePaymentCommand('invoice-1', '10.00', 'EUR', '2026-09-20', 'receipt', '', 'BANK-3', 'manual'));
adminPaymentAssert($invoicePort->statuses[2] === 'overpaid', 'Bedrag boven het factuurtotaal moet overpaid opleveren.');

$useCase->execute(new RegisterInvoicePaymentCommand('invoice-1', '-10.00', 'EUR', '2026-09-21', 'refund', '', 'REFUND-1', 'manual'));
adminPaymentAssert($invoicePort->statuses[3] === 'paid', 'Terugbetaling moet het afgeletterde saldo en de status verlagen.');

$retry = $useCase->execute(new RegisterInvoicePaymentCommand('invoice-1', '40.00', 'EUR', '2026-09-18', 'receipt', '', 'BANK-1', 'bank_import', 'ext-1'));
adminPaymentAssert($retry === $first && count($payments->payments) === 4, 'Zelfde externe referentie moet idempotent hetzelfde paymentobject teruggeven.');

adminPaymentAssertThrows(DuplicatePaymentReferenceException::class, function () use ($useCase): void {
    $useCase->execute(new RegisterInvoicePaymentCommand('invoice-1', '41.00', 'EUR', '2026-09-18', 'receipt', '', 'BANK-1', 'bank_import', 'ext-1'));
}, 'Zelfde externe referentie met andere inhoud moet conflicteren.');
adminPaymentAssertThrows(InvalidArgumentException::class, function () use ($useCase): void {
    $useCase->execute(new RegisterInvoicePaymentCommand('invoice-1', '10.00', 'USD'));
}, 'Betaling in andere valuta dan de factuur moet worden geweigerd.');

$emptyTransactions = new PaymentTestTransactions();
$emptyPayments = new MemoryPayments($emptyTransactions);
$emptyPort = new MemoryInvoicePaymentPort($emptyTransactions, new PayableInvoice(
    $tenantId,
    'invoice-empty',
    '2026-0002',
    InvoiceStatus::FINALIZED,
    new Money(12100, $currency)
));
$emptyUseCase = new RegisterInvoicePayment(
    new TenantContext($tenantId),
    new PaymentTestIds(),
    $emptyTransactions,
    $emptyPayments,
    $emptyPort,
    new _Time(strtotime('2026-09-18 12:00:00 UTC'))
);
adminPaymentAssertThrows(DomainException::class, function () use ($emptyUseCase): void {
    $emptyUseCase->execute(new RegisterInvoicePaymentCommand('invoice-empty', '-1.00', 'EUR', '2026-09-18', 'refund'));
}, 'Terugbetaling mag het eerder ontvangen bedrag niet overschrijden.');

adminPaymentAssert(count($transactions->trace) >= 12, 'Iedere geldige of conflicterende registratie moet een expliciete transactiegrens gebruiken.');

echo "AdminPayment invoice allocation tests passed.\n";
