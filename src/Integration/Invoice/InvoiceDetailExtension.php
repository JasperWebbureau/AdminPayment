<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Event\AjaxEvent;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;
use Flexgrid\Modules\AdminInvoice\Contract\InvoiceDetailExtensionInterface;
use Flexgrid\Response\TemplateResponse;

final class InvoiceDetailExtension implements InvoiceDetailExtensionInterface
{
    private const TARGET_TYPE = 'invoice';

    public function render(InvoiceDetailContext $context): string
    {
        $allocations = InvoicePaymentFactory::createListTargetPayments()->execute(
            self::TARGET_TYPE,
            $context->getPublicId(),
            $context->getGrossTotal()->getCurrency()
        );
        $event = new AjaxEvent(InvoicePaymentAction::class, 'register');
        $event->setMinimumAccessLevel(2);
        $viewModel = InvoicePaymentFactory::createPresenter()->present(
            $context,
            $allocations,
            $event->getName()
        );
        $viewModel['mollieEnabled'] = InvoicePaymentFactory::mollieLinksEnabled();
        $viewModel['mollieLink'] = $viewModel['mollieEnabled']
            ? InvoicePaymentFactory::createMollieLinks()->find($context->getPublicId()) : null;
        $linkEvent = new AjaxEvent(InvoicePaymentAction::class, 'createLink');
        $linkEvent->setMinimumAccessLevel(2);
        $viewModel['createLinkAction'] = $linkEvent->getName();
        $viewModel['canCreateLink'] = $viewModel['mollieEnabled']
            && $viewModel['mollieLink'] === null
            && $context->canRegisterPayments()
            && $context->getPaymentStatus() === \Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus::UNPAID
            && $context->getGrossTotal()->getMinorUnits() > 0;

        return (string)new TemplateResponse(
            'Flexgrid/Modules/AdminPayment/src/Templates/Invoice/Panel.php',
            $viewModel
        );
    }
}
