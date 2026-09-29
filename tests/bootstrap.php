<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$modulesRoot = dirname($moduleRoot);

require_once $modulesRoot . '/AdminCore/tests/bootstrap.php';
foreach ([
    'Domain/ValueObject/InvoiceStatus.php',
    'Domain/ValueObject/PaymentStatus.php',
    'Application/ReadModel/PayableInvoice.php',
    'Application/ReadModel/InvoiceDetailContext.php',
    'Contract/InvoicePaymentPortInterface.php',
    'Contract/InvoicePaymentDetailPortInterface.php',
    'Contract/InvoiceDetailExtensionInterface.php',
] as $file) {
    require_once $modulesRoot . '/AdminInvoice/src/' . $file;
}
foreach ([
    'Domain/ValueObject/BookingDate.php',
    'Domain/ValueObject/PaymentType.php',
    'Domain/Model/PaymentAllocation.php',
    'Domain/Model/Payment.php',
    'Exception/DuplicatePaymentReferenceException.php',
    'Contract/PaymentRepositoryInterface.php',
    'Application/Command/RegisterInvoicePaymentCommand.php',
    'Application/ReadModel/PaymentAllocationView.php',
    'Application/UseCase/ListTargetPayments.php',
    'Integration/Invoice/RegisterInvoicePayment.php',
    'Integration/Invoice/InvoicePaymentPresenter.php',
    'Integration/Invoice/PaymentLinkGatewayInterface.php',
    'Integration/Invoice/InvoiceMollieLinks.php',
    'Infrastructure/Persistence/PdoPaymentRepository.php',
] as $file) {
    require_once $moduleRoot . '/src/' . $file;
}

function adminPaymentAssert($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function adminPaymentAssertThrows(string $exceptionClass, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $exceptionClass) {
            return;
        }
        throw new RuntimeException($message . ' Ontvangen: ' . get_class($throwable));
    }
    throw new RuntimeException($message . ' Er werd geen exception gegooid.');
}
