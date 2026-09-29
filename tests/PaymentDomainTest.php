<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminPayment\Domain\Model\Payment;
use Flexgrid\Modules\AdminPayment\Domain\Model\PaymentAllocation;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\BookingDate;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\PaymentType;

$currency = Currency::euro();
$payment = new Payment(
    'payment-1',
    new TenantId('payment-tenant'),
    new PaymentType(PaymentType::RECEIPT),
    new Money(10000, $currency),
    new BookingDate('2026-09-18'),
    'Ontvangen betaling',
    'BANK-1',
    'bank_import',
    'transaction-1'
);
$payment->allocate(new PaymentAllocation('allocation-1', 'invoice', 'invoice-1', new Money(6000, $currency), 1770000000));
$payment->allocate(new PaymentAllocation('allocation-2', 'invoice', 'invoice-2', new Money(4000, $currency), 1770000001));
adminPaymentAssert($payment->isFullyAllocated(), 'Meerdere allocaties mogen samen het betaalbedrag exact afletteren.');
adminPaymentAssert($payment->getAllocatedAmount()->getMinorUnits() === 10000, 'Afgeletterd totaal moet Money gebruiken.');

adminPaymentAssertThrows(DomainException::class, function () use ($payment, $currency): void {
    $payment->allocate(new PaymentAllocation('allocation-3', 'invoice', 'invoice-3', new Money(1, $currency), 1770000002));
}, 'Allocaties boven het betaalbedrag moeten worden geweigerd.');
adminPaymentAssertThrows(DomainException::class, function () use ($payment, $currency): void {
    $payment->allocate(new PaymentAllocation('allocation-4', 'invoice', 'invoice-1', new Money(1, $currency), 1770000002));
}, 'Eén betaling mag hetzelfde doel niet dubbel alloceren.');
adminPaymentAssertThrows(InvalidArgumentException::class, function () use ($currency): void {
    new Payment('wrong-sign', new TenantId('payment-tenant'), new PaymentType('refund'), new Money(100, $currency), new BookingDate('2026-09-18'));
}, 'Refund en bedragsteken moeten overeenkomen.');

$refund = new Payment(
    'refund-1',
    new TenantId('payment-tenant'),
    new PaymentType(PaymentType::REFUND),
    new Money(-2500, $currency),
    new BookingDate('2026-09-19')
);
$refund->allocate(new PaymentAllocation('refund-allocation', 'invoice', 'invoice-1', new Money(-2500, $currency), 1770000003));
adminPaymentAssert($refund->isFullyAllocated(), 'Signed bedragen moeten een terugbetaling veilig kunnen modelleren.');

adminPaymentAssertThrows(InvalidArgumentException::class, function (): void {
    new BookingDate('2026-02-30');
}, 'Ongeldige kalenderdatum moet worden geweigerd.');

echo "AdminPayment domain tests passed.\n";
