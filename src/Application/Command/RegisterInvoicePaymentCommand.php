<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Application\Command;

final class RegisterInvoicePaymentCommand
{
    /** @var string */ private $invoicePublicId;
    /** @var string */ private $amount;
    /** @var string */ private $currency;
    /** @var string */ private $bookedOn;
    /** @var string */ private $type;
    /** @var string */ private $description;
    /** @var string */ private $reference;
    /** @var string */ private $source;
    /** @var string */ private $externalId;

    public function __construct(
        string $invoicePublicId,
        string $amount,
        string $currency = 'EUR',
        string $bookedOn = '',
        string $type = 'receipt',
        string $description = '',
        string $reference = '',
        string $source = 'manual',
        string $externalId = ''
    ) {
        $this->invoicePublicId = $invoicePublicId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->bookedOn = $bookedOn;
        $this->type = $type;
        $this->description = $description;
        $this->reference = $reference;
        $this->source = $source;
        $this->externalId = $externalId;
    }

    public function getInvoicePublicId(): string { return $this->invoicePublicId; }
    public function getAmount(): string { return $this->amount; }
    public function getCurrency(): string { return $this->currency; }
    public function getBookedOn(): string { return $this->bookedOn; }
    public function getType(): string { return $this->type; }
    public function getDescription(): string { return $this->description; }
    public function getReference(): string { return $this->reference; }
    public function getSource(): string { return $this->source; }
    public function getExternalId(): string { return $this->externalId; }
}
