<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Application\ReadModel;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\BookingDate;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\PaymentType;

final class PaymentAllocationView
{
    /** @var string */ private $paymentPublicId;
    /** @var string */ private $allocationPublicId;
    /** @var PaymentType */ private $type;
    /** @var Money */ private $amount;
    /** @var BookingDate */ private $bookedOn;
    /** @var string */ private $description;
    /** @var string */ private $reference;
    /** @var string */ private $source;

    public function __construct(
        string $paymentPublicId,
        string $allocationPublicId,
        PaymentType $type,
        Money $amount,
        BookingDate $bookedOn,
        string $description,
        string $reference,
        string $source
    ) {
        $this->paymentPublicId = $paymentPublicId;
        $this->allocationPublicId = $allocationPublicId;
        $this->type = $type;
        $this->amount = $amount;
        $this->bookedOn = $bookedOn;
        $this->description = trim($description);
        $this->reference = trim($reference);
        $this->source = strtolower(trim($source));
    }

    public function getPaymentPublicId(): string { return $this->paymentPublicId; }
    public function getAllocationPublicId(): string { return $this->allocationPublicId; }
    public function getType(): PaymentType { return $this->type; }
    public function getAmount(): Money { return $this->amount; }
    public function getBookedOn(): BookingDate { return $this->bookedOn; }
    public function getDescription(): string { return $this->description; }
    public function getReference(): string { return $this->reference; }
    public function getSource(): string { return $this->source; }
}
