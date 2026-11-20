<?php
declare(strict_types=1);
namespace Flexgrid\Modules\AdminPayment\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminPayment\Contract\PaymentOverviewRepositoryInterface;

final class GetPaymentOverview
{
    private $tenant;
    private $repository;
    public function __construct(TenantContext $tenant, PaymentOverviewRepositoryInterface $repository)
    {
        $this->tenant = $tenant;
        $this->repository = $repository;
    }

    public function execute(string $month): array
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        if (!$start || $start->format('Y-m') !== $month) { throw new \InvalidArgumentException('Kies een geldige maand.'); }
        $overview = $this->repository->overview($this->tenant->getTenantId(), $start->format('Y-m-d'), $start->modify('+1 month')->format('Y-m-d'));
        foreach ($overview['totals'] as $total) {
            foreach (['receipts_minor', 'refunds_minor', 'net_minor'] as $field) {
                if (filter_var($total[$field], FILTER_VALIDATE_INT) === false) { throw new \OverflowException('Het betalingstotaal valt buiten het ondersteunde integerbereik.'); }
            }
        }
        return $overview;
    }
}
