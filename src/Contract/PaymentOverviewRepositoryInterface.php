<?php
declare(strict_types=1);
namespace Flexgrid\Modules\AdminPayment\Contract;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;

interface PaymentOverviewRepositoryInterface
{
    /** Signed ledger totals per currency and recent receipts/refunds, restricted to one tenant and date range. */
    public function overview(TenantId $tenantId, string $from, string $until): array;
}
