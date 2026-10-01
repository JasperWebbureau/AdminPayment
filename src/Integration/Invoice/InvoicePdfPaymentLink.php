<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;

/** Optional payment data for finalized invoice PDFs, including mailed copies. */
final class InvoicePdfPaymentLink
{
    public function forInvoice(Invoice $invoice): ?array
    {
        if (!InvoicePaymentFactory::mollieLinksEnabled()
            || $invoice->getStatus()->getValue() !== InvoiceStatus::FINALIZED
            || $invoice->getPaymentStatus()->getValue() !== PaymentStatus::UNPAID) {
            return null;
        }
        $links = InvoicePaymentFactory::createMollieLinks();
        $link = $links->find($invoice->getPublicId());
        if ($link === null) {
            $context = InvoicePaymentFactory::createInvoicePort()->findPaymentDetailByPublicId(
                InvoicePaymentFactory::tenantContext()->getTenantId(), $invoice->getPublicId()
            );
            if ($context === null) { return null; }
            $link = $links->create($context, '', InvoicePaymentFactory::mollieRedirectUrl());
        }
        if (($link['status'] ?? '') !== 'ready' || empty($link['url'])) {
            throw new \DomainException('De Mollie-betaallink vereist controle voordat deze factuur als PDF kan worden verstuurd.');
        }
        $url = (string)$link['url'];
        return ['payment_url' => $url, 'payment_qr_data_uri' => (new InvoicePaymentQrCode())->dataUri($url)];
    }
}
