<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Contract\PublicIdGeneratorInterface;
use Flexgrid\Modules\AdminCore\Contract\TransactionManagerInterface;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\PayableInvoice;
use Flexgrid\Modules\AdminInvoice\Contract\InvoicePaymentPortInterface;
use Flexgrid\Modules\AdminPayment\Application\Command\RegisterInvoicePaymentCommand;
use Flexgrid\Modules\AdminPayment\Contract\PaymentRepositoryInterface;
use Flexgrid\Modules\AdminPayment\Domain\Model\Payment;
use Flexgrid\Modules\AdminPayment\Domain\Model\PaymentAllocation;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\BookingDate;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\PaymentType;
use Flexgrid\Modules\AdminPayment\Exception\DuplicatePaymentReferenceException;
use Flexgrid\Utils\_Time;

final class RegisterInvoicePayment
{
    private const TARGET_TYPE = 'invoice';

    /** @var TenantContext */ private $tenantContext;
    /** @var PublicIdGeneratorInterface */ private $publicIds;
    /** @var TransactionManagerInterface */ private $transactions;
    /** @var PaymentRepositoryInterface */ private $payments;
    /** @var InvoicePaymentPortInterface */ private $invoices;
    /** @var _Time */ private $clock;

    public function __construct(
        TenantContext $tenantContext,
        PublicIdGeneratorInterface $publicIds,
        TransactionManagerInterface $transactions,
        PaymentRepositoryInterface $payments,
        InvoicePaymentPortInterface $invoices,
        _Time $clock
    ) {
        $this->tenantContext = $tenantContext;
        $this->publicIds = $publicIds;
        $this->transactions = $transactions;
        $this->payments = $payments;
        $this->invoices = $invoices;
        $this->clock = $clock;
    }

    public function execute(RegisterInvoicePaymentCommand $command): Payment
    {
        $input = $this->normalize($command);
        try {
            return $this->transactions->transactional(function () use ($input): Payment {
                $tenantId = $this->tenantContext->getTenantId();
                $invoice = $this->invoices->findPayableByPublicIdForUpdate($tenantId, $input['invoice_public_id']);
                if ($invoice === null) {
                    throw new \DomainException('Factuur niet gevonden.');
                }
                if ($input['external_id'] !== '') {
                    $existing = $this->payments->findByExternalReference(
                        $tenantId,
                        $input['source'],
                        $input['external_id']
                    );
                    if ($existing !== null) {
                        $this->assertIdempotentMatch($existing, $input);
                        return $existing;
                    }
                }
                $this->assertPayable($invoice, $input['amount']);

                $timestamp = (int)$this->clock->get();
                $payment = new Payment(
                    $this->publicIds->generate(),
                    $tenantId,
                    $input['type'],
                    $input['amount'],
                    $input['booked_on'],
                    $input['description'],
                    $input['reference'],
                    $input['source'],
                    $input['external_id']
                );
                $payment->allocate(new PaymentAllocation(
                    $this->publicIds->generate(),
                    self::TARGET_TYPE,
                    $invoice->getPublicId(),
                    $input['amount'],
                    $timestamp
                ));
                $this->payments->insert($payment, $timestamp);
                $allocated = $this->payments->getAllocatedTotal(
                    $tenantId,
                    self::TARGET_TYPE,
                    $invoice->getPublicId(),
                    $invoice->getGrossTotal()->getCurrency()
                );
                if ($allocated->getMinorUnits() < 0) {
                    throw new \DomainException('Terugbetalingen mogen het ontvangen bedrag op de factuur niet overschrijden.');
                }
                $this->invoices->synchronizePaymentStatus($invoice, $allocated, $timestamp);

                return $payment;
            });
        } catch (DuplicatePaymentReferenceException $exception) {
            if ($input['external_id'] === '') {
                throw $exception;
            }
            $existing = $this->payments->findByExternalReference(
                $this->tenantContext->getTenantId(),
                $input['source'],
                $input['external_id']
            );
            if ($existing === null) {
                throw $exception;
            }
            $this->assertIdempotentMatch($existing, $input);
            return $existing;
        }
    }

    private function normalize(RegisterInvoicePaymentCommand $command): array
    {
        $invoicePublicId = trim($command->getInvoicePublicId());
        if ($invoicePublicId === '' || strlen($invoicePublicId) > 64) {
            throw new \InvalidArgumentException('Een geldige publieke factuur-id is verplicht.');
        }
        $currency = new Currency($command->getCurrency());
        $amount = Money::fromDecimal($command->getAmount(), $currency);
        $type = new PaymentType($command->getType());
        $bookedOnValue = trim($command->getBookedOn());
        $bookedOn = new BookingDate(
            $bookedOnValue !== '' ? $bookedOnValue : gmdate('Y-m-d', (int)$this->clock->get())
        );
        $description = trim($command->getDescription());
        $reference = trim($command->getReference());
        $source = strtolower(trim($command->getSource()));
        $externalId = trim($command->getExternalId());
        if (($type->getValue() === PaymentType::RECEIPT && $amount->getMinorUnits() <= 0)
            || ($type->getValue() === PaymentType::REFUND && $amount->getMinorUnits() >= 0)
        ) {
            throw new \InvalidArgumentException('Betalingstype en bedragsteken komen niet overeen.');
        }
        if (strlen($description) > 255 || strlen($reference) > 255) {
            throw new \InvalidArgumentException('Omschrijving en referentie zijn maximaal 255 tekens.');
        }
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $source) !== 1 || strlen($externalId) > 128) {
            throw new \InvalidArgumentException('Betaalbron of externe referentie is ongeldig.');
        }

        return [
            'invoice_public_id' => $invoicePublicId,
            'amount' => $amount,
            'type' => $type,
            'booked_on' => $bookedOn,
            'description' => $description ?: ($type->getValue() === PaymentType::REFUND ? 'Terugbetaling' : 'Ontvangen betaling'),
            'reference' => $reference,
            'source' => $source,
            'external_id' => $externalId,
        ];
    }

    private function assertPayable(PayableInvoice $invoice, Money $amount): void
    {
        if (!$invoice->isPayable()) {
            throw new \DomainException('Alleen een definitieve, niet-gecrediteerde factuur kan betalingen ontvangen.');
        }
        if (!$invoice->getGrossTotal()->getCurrency()->equals($amount->getCurrency())) {
            throw new \InvalidArgumentException('Betaling gebruikt een andere valuta dan de factuur.');
        }
        if ($invoice->getGrossTotal()->getMinorUnits() <= 0) {
            throw new \DomainException('Factuur zonder positief eindtotaal kan geen betaling ontvangen.');
        }
    }

    private function assertIdempotentMatch(Payment $payment, array $input): void
    {
        $allocations = $payment->getAllocations();
        $allocation = $allocations[0] ?? null;
        if (!$payment->getAmount()->equals($input['amount'])
            || $payment->getType()->getValue() !== $input['type']->getValue()
            || $payment->getBookedOn()->getValue() !== $input['booked_on']->getValue()
            || count($allocations) !== 1
            || !$allocation instanceof PaymentAllocation
            || $allocation->getTargetType() !== self::TARGET_TYPE
            || $allocation->getTargetPublicId() !== $input['invoice_public_id']
        ) {
            throw new DuplicatePaymentReferenceException(
                'Externe betaalreferentie bestaat al met andere gegevens.'
            );
        }
    }
}
