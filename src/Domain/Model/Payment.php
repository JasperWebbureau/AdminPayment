<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Domain\Model;

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\BookingDate;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\PaymentType;

final class Payment
{
    /** @var string */ private $publicId;
    /** @var TenantId */ private $tenantId;
    /** @var PaymentType */ private $type;
    /** @var Money */ private $amount;
    /** @var BookingDate */ private $bookedOn;
    /** @var string */ private $description;
    /** @var string */ private $reference;
    /** @var string */ private $source;
    /** @var string */ private $externalId;
    /** @var PaymentAllocation[] */ private $allocations = [];

    public function __construct(
        string $publicId,
        TenantId $tenantId,
        PaymentType $type,
        Money $amount,
        BookingDate $bookedOn,
        string $description = '',
        string $reference = '',
        string $source = 'manual',
        string $externalId = ''
    ) {
        $publicId = trim($publicId);
        $description = trim($description);
        $reference = trim($reference);
        $source = strtolower(trim($source));
        $externalId = trim($externalId);
        if ($publicId === '' || strlen($publicId) > 64) {
            throw new \InvalidArgumentException('Betaling vereist een geldige publieke id.');
        }
        if ($amount->isZero()) {
            throw new \InvalidArgumentException('Betaling vereist een niet-nulbedrag.');
        }
        if ($type->getValue() === PaymentType::RECEIPT && $amount->getMinorUnits() < 0) {
            throw new \InvalidArgumentException('Een ontvangst vereist een positief bedrag.');
        }
        if ($type->getValue() === PaymentType::REFUND && $amount->getMinorUnits() > 0) {
            throw new \InvalidArgumentException('Een terugbetaling vereist een negatief bedrag.');
        }
        if (strlen($description) > 255 || strlen($reference) > 255) {
            throw new \InvalidArgumentException('Omschrijving en referentie zijn maximaal 255 tekens.');
        }
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $source) !== 1) {
            throw new \InvalidArgumentException('Betaling vereist een geldige bron.');
        }
        if (strlen($externalId) > 128) {
            throw new \InvalidArgumentException('Externe betaalreferentie is maximaal 128 tekens.');
        }

        $this->publicId = $publicId;
        $this->tenantId = $tenantId;
        $this->type = $type;
        $this->amount = $amount;
        $this->bookedOn = $bookedOn;
        $this->description = $description;
        $this->reference = $reference;
        $this->source = $source;
        $this->externalId = $externalId;
    }

    public function allocate(PaymentAllocation $allocation): void
    {
        if (!$allocation->getAmount()->getCurrency()->equals($this->amount->getCurrency())) {
            throw new \InvalidArgumentException('Allocatie gebruikt een andere valuta dan de betaling.');
        }
        if (($this->amount->getMinorUnits() > 0) !== ($allocation->getAmount()->getMinorUnits() > 0)) {
            throw new \DomainException('Allocatie en betaling moeten dezelfde bedragstekenrichting hebben.');
        }
        foreach ($this->allocations as $existing) {
            if ($existing->getTargetType() === $allocation->getTargetType()
                && $existing->getTargetPublicId() === $allocation->getTargetPublicId()
            ) {
                throw new \DomainException('Betaling is al op dit doel afgeletterd.');
            }
        }
        $allocated = $this->getAllocatedAmount()->add($allocation->getAmount());
        if (abs($allocated->getMinorUnits()) > abs($this->amount->getMinorUnits())) {
            throw new \DomainException('Allocaties mogen het betaalbedrag niet overschrijden.');
        }
        $this->allocations[] = $allocation;
    }

    public function getAllocatedAmount(): Money
    {
        $allocated = Money::zero($this->amount->getCurrency());
        foreach ($this->allocations as $allocation) {
            $allocated = $allocated->add($allocation->getAmount());
        }
        return $allocated;
    }

    public function isFullyAllocated(): bool
    {
        return $this->getAllocatedAmount()->equals($this->amount);
    }

    public function getPublicId(): string { return $this->publicId; }
    public function getTenantId(): TenantId { return $this->tenantId; }
    public function getType(): PaymentType { return $this->type; }
    public function getAmount(): Money { return $this->amount; }
    public function getCurrency(): Currency { return $this->amount->getCurrency(); }
    public function getBookedOn(): BookingDate { return $this->bookedOn; }
    public function getDescription(): string { return $this->description; }
    public function getReference(): string { return $this->reference; }
    public function getSource(): string { return $this->source; }
    public function getExternalId(): string { return $this->externalId; }
    /** @return PaymentAllocation[] */ public function getAllocations(): array { return $this->allocations; }
}
