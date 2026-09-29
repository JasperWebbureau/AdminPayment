<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;
use Flexgrid\Modules\AdminPayment\Application\ReadModel\PaymentAllocationView;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\PaymentType;
use Flexgrid\Utils\_Time;

final class InvoicePaymentPresenter
{
    /** @var _Time */ private $clock;

    public function __construct(_Time $clock)
    {
        $this->clock = $clock;
    }

    /** @param PaymentAllocationView[] $allocations */
    public function present(InvoiceDetailContext $context, array $allocations, string $registerAction): array
    {
        $allocated = Money::zero($context->getGrossTotal()->getCurrency());
        $rows = [];
        foreach ($allocations as $allocation) {
            if (!$allocation instanceof PaymentAllocationView) {
                throw new \InvalidArgumentException('Paymentoverzicht bevat een ongeldig item.');
            }
            $allocated = $allocated->add($allocation->getAmount());
            $rows[] = [
                'id' => $allocation->getAllocationPublicId(),
                'cells' => [
                    'booked_on' => $allocation->getBookedOn()->getValue(),
                    'description' => [
                        'value' => $allocation->getDescription(),
                        'secondary' => $allocation->getReference() !== ''
                            ? 'Referentie: ' . $allocation->getReference()
                            : '',
                        'title' => true,
                    ],
                    'source' => $this->sourceLabel($allocation->getSource()),
                    'type' => [
                        'value' => $allocation->getType()->getValue() === PaymentType::REFUND
                            ? 'Terugbetaling'
                            : 'Ontvangst',
                        'badge' => $allocation->getType()->getValue() === PaymentType::REFUND
                            ? 'warning'
                            : 'success',
                    ],
                    'amount' => $this->money($allocation->getAmount()),
                ],
            ];
        }

        $outstanding = $context->getGrossTotal()->subtract($allocated);
        $balanceLabel = 'Openstaand';
        $balance = $outstanding;
        if ($outstanding->getMinorUnits() < 0) {
            $balanceLabel = 'Te veel ontvangen';
            $balance = $outstanding->negate();
        }

        return [
            'invoicePublicId' => $context->getPublicId(),
            'invoiceNumber' => $context->getNumber(),
            'currency' => $context->getGrossTotal()->getCurrency()->getCode(),
            'canRegister' => $context->canRegisterPayments(),
            'registerAction' => $registerAction,
            'bookedOn' => gmdate('Y-m-d', (int)$this->clock->get()),
            'suggestedAmount' => $outstanding->getMinorUnits() > 0
                ? $outstanding->format('.', '')
                : '',
            'grossTotal' => $this->money($context->getGrossTotal()),
            'allocatedTotal' => $this->money($allocated),
            'balanceLabel' => $balanceLabel,
            'balance' => $this->money($balance),
            'paymentStatusLabel' => $this->statusLabel($context->getPaymentStatus()),
            'paymentStatusTone' => $this->statusTone($context->getPaymentStatus()),
            'table' => [
                'id' => 'admin-invoice-payments',
                'label' => 'Betalingen voor factuur ' . $context->getNumber(),
                'compact' => true,
                'columns' => [
                    ['key' => 'booked_on', 'label' => 'Boekdatum'],
                    ['key' => 'description', 'label' => 'Omschrijving'],
                    ['key' => 'source', 'label' => 'Bron'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'amount', 'label' => 'Bedrag', 'align' => 'right'],
                ],
                'rows' => $rows,
                'empty' => [
                    'title' => 'Nog geen betalingen',
                    'message' => 'Registreer de eerste ontvangst zodra deze binnen is.',
                    'icon' => 'fas fa-money-bill-transfer',
                ],
            ],
        ];
    }

    private function money(Money $money): string
    {
        $prefix = $money->getCurrency()->getCode() === 'EUR'
            ? '€ '
            : $money->getCurrency()->getCode() . ' ';

        return $prefix . $money->format();
    }

    private function sourceLabel(string $source): string
    {
        if ($source === 'manual') {
            return 'Handmatig';
        }
        if ($source === 'bank_import') {
            return 'Bankimport';
        }

        return ucfirst(str_replace('_', ' ', $source));
    }

    private function statusLabel(string $status): string
    {
        $labels = [
            PaymentStatus::UNPAID => 'Onbetaald',
            PaymentStatus::PARTIALLY_PAID => 'Deels betaald',
            PaymentStatus::PAID => 'Betaald',
            PaymentStatus::OVERPAID => 'Te veel betaald',
        ];

        return $labels[$status] ?? 'Onbekend';
    }

    private function statusTone(string $status): string
    {
        if ($status === PaymentStatus::PAID) {
            return 'success';
        }
        if ($status === PaymentStatus::PARTIALLY_PAID || $status === PaymentStatus::OVERPAID) {
            return 'warning';
        }

        return 'danger';
    }
}
