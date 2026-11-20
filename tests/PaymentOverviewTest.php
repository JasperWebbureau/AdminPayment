<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$repository = new class implements \Flexgrid\Modules\AdminPayment\Contract\PaymentOverviewRepositoryInterface {
    public $arguments;
    public $result = ['totals' => [], 'payments' => []];
    public function overview(\Flexgrid\Modules\AdminCore\ValueObject\TenantId $tenantId, string $from, string $until): array
    {
        $this->arguments = [$tenantId->toString(), $from, $until];
        return $this->result;
    }
};
$query = new \Flexgrid\Modules\AdminPayment\Application\UseCase\GetPaymentOverview(
    new \Flexgrid\Modules\AdminCore\Context\TenantContext(new \Flexgrid\Modules\AdminCore\ValueObject\TenantId('payment-overview-fixture')), $repository
);
$query->execute('2028-02');
adminPaymentAssert($repository->arguments === ['payment-overview-fixture', '2028-02-01', '2028-03-01'], 'Payment overview must carry its tenant and leap-month range.');
$query->execute('2026-12');
adminPaymentAssert($repository->arguments[2] === '2027-01-01', 'Payment overview must cross year boundary correctly.');
foreach (['2026-13', '2026-00', '2026-1', 'invalid', '2026-01-01'] as $invalid) {
    try { $query->execute($invalid); throw new RuntimeException('Invalid payment month was accepted.'); }
    catch (InvalidArgumentException $expected) { }
}
$repository->result = ['totals' => [['currency' => 'EUR', 'receipts_minor' => '999999999999999999999', 'refunds_minor' => '0', 'net_minor' => '999999999999999999999']], 'payments' => []];
try { $query->execute('2026-01'); throw new RuntimeException('Out-of-range ledger total was silently truncated.'); }
catch (OverflowException $expected) { }
echo "PaymentOverviewTest: 8 checks passed.\n";
