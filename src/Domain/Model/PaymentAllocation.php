<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Domain\Model;

use Flexgrid\Modules\AdminCore\ValueObject\Money;

final class PaymentAllocation
{
    /** @var string */ private $publicId;
    /** @var string */ private $targetType;
    /** @var string */ private $targetPublicId;
    /** @var Money */ private $amount;
    /** @var int */ private $allocatedAt;

    public function __construct(
        string $publicId,
        string $targetType,
        string $targetPublicId,
        Money $amount,
        int $allocatedAt
    ) {
        $publicId = trim($publicId);
        $targetType = strtolower(trim($targetType));
        $targetPublicId = trim($targetPublicId);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Allocatie vereist een geldige publieke id.');
        }
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $targetType) !== 1) {
            throw new \InvalidArgumentException('Allocatie vereist een geldig targettype.');
        }
        if ($targetPublicId === '' || strlen($targetPublicId) > 64) {
            throw new \InvalidArgumentException('Allocatie vereist een geldige publieke target-id.');
        }
        if ($amount->isZero() || $allocatedAt < 0) {
            throw new \InvalidArgumentException('Allocatie vereist een niet-nulbedrag en geldig tijdstip.');
        }

        $this->publicId = $publicId;
        $this->targetType = $targetType;
        $this->targetPublicId = $targetPublicId;
        $this->amount = $amount;
        $this->allocatedAt = $allocatedAt;
    }

    public function getPublicId(): string { return $this->publicId; }
    public function getTargetType(): string { return $this->targetType; }
    public function getTargetPublicId(): string { return $this->targetPublicId; }
    public function getAmount(): Money { return $this->amount; }
    public function getAllocatedAt(): int { return $this->allocatedAt; }
}
