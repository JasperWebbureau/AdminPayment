<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Contract;

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminPayment\Domain\Model\Payment;

interface PaymentRepositoryInterface
{
    public function insert(Payment $payment, int $createdAt): void;

    public function findByExternalReference(TenantId $tenantId, string $source, string $externalId): ?Payment;

    public function getAllocatedTotal(
        TenantId $tenantId,
        string $targetType,
        string $targetPublicId,
        Currency $currency
    ): Money;

    /** @return \Flexgrid\Modules\AdminPayment\Application\ReadModel\PaymentAllocationView[] */
    public function listAllocationsForTarget(
        TenantId $tenantId,
        string $targetType,
        string $targetPublicId,
        Currency $currency
    ): array;
}
