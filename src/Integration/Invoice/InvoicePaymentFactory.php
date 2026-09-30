<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Database\Connection;
use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Infrastructure\PdoTransactionManager;
use Flexgrid\Modules\AdminCore\Infrastructure\UuidV4Generator;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoInvoicePaymentPort;
use Flexgrid\Modules\AdminPayment\Application\Command\RegisterInvoicePaymentCommand;
use Flexgrid\Modules\AdminPayment\Application\UseCase\ListTargetPayments;
use Flexgrid\Modules\AdminPayment\Infrastructure\Persistence\PdoPaymentRepository;
use Flexgrid\Utils\_Time;

final class InvoicePaymentFactory
{
    public static function createRegisterInvoicePayment(): RegisterInvoicePayment
    {
        $connection = self::connection();

        return new RegisterInvoicePayment(
            self::tenantContext(),
            new UuidV4Generator(),
            new PdoTransactionManager($connection),
            new PdoPaymentRepository($connection),
            new PdoInvoicePaymentPort($connection),
            new _Time()
        );
    }

    public static function createListTargetPayments(): ListTargetPayments
    {
        return new ListTargetPayments(
            self::tenantContext(),
            new PdoPaymentRepository(self::connection())
        );
    }

    public static function createPresenter(): InvoicePaymentPresenter
    {
        return new InvoicePaymentPresenter(new _Time());
    }

    public static function createManualEditor(): ManualInvoicePaymentEditor
    {
        return new ManualInvoicePaymentEditor(self::connection(), self::tenantContext()->getTenantId());
    }

    public static function mollieLinksEnabled(): bool
    {
        return defined('__ADMIN_INVOICE_MOLLIE_KEY__')
            && preg_match('/^(test|live)_[A-Za-z0-9]+$/D', trim((string)constant('__ADMIN_INVOICE_MOLLIE_KEY__'))) === 1
            && class_exists(\Mollie\Api\MollieApiClient::class);
    }

    public static function createMollieLinks(): InvoiceMollieLinks
    {
        if (!self::mollieLinksEnabled()) {
            throw new \LogicException('Mollie-factuurbetalingen zijn niet geconfigureerd.');
        }
        return new InvoiceMollieLinks(
            self::connection(),
            self::tenantContext()->getTenantId(),
            new MolliePaymentLinkGateway((string)constant('__ADMIN_INVOICE_MOLLIE_KEY__')),
            static function (string $invoiceId, string $amount, string $currency, string $paymentId, string $bookedOn): void {
                self::createRegisterInvoicePayment()->execute(new RegisterInvoicePaymentCommand(
                    $invoiceId, $amount, $currency, $bookedOn, 'receipt',
                    'Mollie factuurbetaling', $paymentId, 'mollie_invoice', $paymentId
                ));
            }
        );
    }

    public static function createInvoicePort(): PdoInvoicePaymentPort
    {
        return new PdoInvoicePaymentPort(self::connection());
    }

    public static function tenantContext(): TenantContext
    {
        if (!defined('__ADMIN_TENANT_ID__')) {
            throw new \LogicException('Definieer __ADMIN_TENANT_ID__ expliciet voor de Admin-modules.');
        }

        return new TenantContext(new TenantId((string)constant('__ADMIN_TENANT_ID__')));
    }

    private static function connection(): \PDO
    {
        return Connection::getConnections();
    }
}
