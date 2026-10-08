<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Modules\AdminInvoice\Contract\InvoiceCollectionExtensionInterface;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;

final class InvoiceCollectionExtension implements InvoiceCollectionExtensionInterface
{
    public function prepare(Invoice $invoice): string
    {
        if (!InvoicePaymentFactory::directDebitReady()) {
            if (!DirectDebitSettings::enabled()) { return ''; }
            throw new \LogicException('Incasso is ingeschakeld, maar de configuratie of het schema is nog niet klaar.');
        }
        return InvoicePaymentFactory::createDirectDebit()->prepare($invoice);
    }

    public function noticeSent(Invoice $invoice): void
    {
        if (InvoicePaymentFactory::directDebitReady()) {
            InvoicePaymentFactory::createDirectDebit()->noticeSent($invoice->getPublicId());
        }
    }

    public function processDue(string $today): array
    {
        if (!InvoicePaymentFactory::directDebitReady()) {
            if (DirectDebitSettings::enabled()) { throw new \LogicException('Incassoconfiguratie of schema ontbreekt.'); }
            return ['started' => 0, 'checked' => 0, 'failed' => 0];
        }
        $service = InvoicePaymentFactory::createDirectDebit();
        $checked = $service->synchronizeOpen();
        $due = $service->runDue($today);
        return ['started' => $due['started'], 'checked' => $checked['checked'], 'failed' => $due['failed'] + $checked['failed']];
    }
}
