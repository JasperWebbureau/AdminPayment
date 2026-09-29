<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Application\UseCase;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminPayment\Contract\PaymentRepositoryInterface;

final class ListTargetPayments
{
    /** @var TenantContext */ private $tenantContext;
    /** @var PaymentRepositoryInterface */ private $payments;

    public function __construct(TenantContext $tenantContext, PaymentRepositoryInterface $payments)
    {
        $this->tenantContext = $tenantContext;
        $this->payments = $payments;
    }

    public function execute(string $targetType, string $targetPublicId, Currency $currency): array
    {
        $targetType = strtolower(trim($targetType));
        $targetPublicId = trim($targetPublicId);
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $targetType) !== 1) {
            throw new \InvalidArgumentException('Ongeldig payment-targettype.');
        }
        if ($targetPublicId === '' || strlen($targetPublicId) > 64) {
            throw new \InvalidArgumentException('Ongeldige publieke target-id.');
        }

        return $this->payments->listAllocationsForTarget(
            $this->tenantContext->getTenantId(),
            $targetType,
            $targetPublicId,
            $currency
        );
    }
}
