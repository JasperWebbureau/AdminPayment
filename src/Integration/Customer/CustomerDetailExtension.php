<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Customer;

use Flexgrid\Modules\AdminCustomer\Application\ReadModel\CustomerDetailContext;
use Flexgrid\Modules\AdminCustomer\Contract\CustomerDetailExtensionInterface;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\InvoicePaymentFactory;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\AdminInvoiceCollectionController;
use Flexgrid\Event\AjaxEvent;
use Flexgrid\Response\TemplateResponse;

final class CustomerDetailExtension implements CustomerDetailExtensionInterface
{
    public function render(CustomerDetailContext $context): string
    {
        $ready = InvoicePaymentFactory::directDebitReady();
        $row = $ready ? InvoicePaymentFactory::createDirectDebit()->customer($context->getPublicId()) : null;
        $actions = [];
        foreach (['setup', 'syncCustomer', 'customerEnabled'] as $method) {
            $event = new AjaxEvent(AdminInvoiceCollectionController::class, $method);
            $event->setMinimumAccessLevel(5);
            $actions[$method] = $event->getName();
        }
        return (string)new TemplateResponse('Flexgrid/Modules/AdminPayment/src/Templates/DirectDebit/Customer.php',
            ['customerId' => $context->getPublicId(), 'active' => $context->isActive(),
                'ready' => $ready, 'debitCustomer' => $row, 'actions' => $actions]);
    }
}
