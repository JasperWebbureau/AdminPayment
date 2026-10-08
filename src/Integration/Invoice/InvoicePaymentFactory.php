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
use Flexgrid\App\Settings\Settings;

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
            new _Time(),
            static function (string $invoiceId, string $source): void {
                if ($source === 'mollie_directdebit') { return; }
                self::assertNoReservedDebit($invoiceId, true);
            }
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
        return defined('__MOLLIE_KEY__')
            && preg_match('/^live_[A-Za-z0-9]+$/D', trim((string)constant('__MOLLIE_KEY__'))) === 1
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
            new MolliePaymentLinkGateway(trim((string)constant('__MOLLIE_KEY__'))),
            static function (string $invoiceId, string $amount, string $currency, string $paymentId, string $bookedOn): void {
                self::createRegisterInvoicePayment()->execute(new RegisterInvoicePaymentCommand(
                    $invoiceId, $amount, $currency, $bookedOn, 'receipt',
                    'Mollie factuurbetaling', $paymentId, 'mollie_invoice', $paymentId
                ));
            },
            static function (string $invoiceId): bool {
                $context = self::createInvoicePort()->findPaymentDetailByPublicId(
                    self::tenantContext()->getTenantId(), $invoiceId
                );
                return $context !== null && $context->getPaymentStatus() === \Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus::UNPAID;
            },
            static function (string $invoiceId): void {
                // Wordt uitgevoerd in de link-reserveringstransactie.
                self::createInvoicePort()->findPayableByPublicIdForUpdate(self::tenantContext()->getTenantId(), $invoiceId);
                self::assertNoReservedDebit($invoiceId);
            }
        );
    }

    public static function mollieRedirectUrl(): string
    {
        new Settings();
        $setting = Settings::get('administration_invoice_mollie_redirect_url');
        $url = is_array($setting) ? trim((string)($setting['value'] ?? '')) : '';
        if ($url !== '' && !preg_match('~^https://[^\s]+$~iD', $url)) {
            throw new \LogicException('Stel een publieke HTTPS-bedankpagina in voor Mollie.');
        }
        return $url;
    }

    public static function createInvoicePort(): PdoInvoicePaymentPort
    {
        return new PdoInvoicePaymentPort(self::connection());
    }

    public static function directDebitConfigured(): bool
    {
        return preg_match('/^(test|live)_[A-Za-z0-9]+$/D', self::directDebitKey()) === 1
            && class_exists(\Mollie\Api\MollieApiClient::class);
    }

    public static function directDebitReady(): bool
    {
        if (!self::directDebitConfigured() || self::directDebitWebhookBase() === '') { return false; }
        return self::directDebitSchemaReady();
    }

    public static function directDebitSchemaReady(): bool
    {
        try {
            foreach (['admin_invoice_debit_customer', 'admin_invoice_direct_debit'] as $table) {
                self::connection()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
            }
            return true;
        } catch (\PDOException $exception) { return false; }
    }

    private static function assertNoReservedDebit(string $invoiceId, bool $manual = false): void
    {
        if (!self::directDebitSchemaReady()) { return; }
        $statement = self::connection()->prepare('SELECT `status` FROM `admin_invoice_direct_debit` WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice');
        $statement->execute([':tenant' => self::tenantContext()->getTenantId()->toString(), ':invoice' => $invoiceId]);
        $status = $statement->fetchColumn();
        $blocked = ['awaiting_notice', 'scheduled', 'creating', 'pending', 'attention'];
        if (!$manual) { $blocked[] = 'paid'; }
        if (in_array($status, $blocked, true)) { throw new \DomainException('Er staat een incasso voor deze factuur gepland of in verwerking. Controleer die eerst.'); }
    }

    public static function createDirectDebit(): InvoiceDirectDebit
    {
        if (!self::directDebitConfigured()) { throw new \LogicException('Mollie-factuurincasso is niet geconfigureerd.'); }
        $state = '\\Flexgrid\\Modules\\AdminCore\\Integration\\Flexgrid\\Service\\AdminModuleState';
        if (class_exists($state) && !$state::isEnabled('AdminPayment')) { throw new \LogicException('AdminPayment is uitgeschakeld.'); }
        if (self::directDebitWebhookBase() === '') {
            throw new \LogicException('Stel een publiek HTTPS-domein in voor de incassowebhook.');
        }
        return new InvoiceDirectDebit(self::connection(), self::tenantContext()->getTenantId(),
            new MollieDirectDebitGateway(self::directDebitKey()),
            static function (string $invoiceId, string $amount, string $currency, string $externalId, string $date, string $type): void {
                self::createRegisterInvoicePayment()->execute(new RegisterInvoicePaymentCommand(
                    $invoiceId, $amount, $currency, $date, $type, 'Mollie automatische incasso',
                    $externalId, 'mollie_directdebit', $externalId));
            },
            static function (string $id) { return \Flexgrid\Modules\AdminInvoice\Service\AdminInvoiceFactory::createGetInvoice()->execute($id); },
            static function (): bool { return DirectDebitSettings::enabled(); },
            static function (): int { return DirectDebitSettings::noticeDays(); },
            self::directDebitWebhookBase(),
            static function (string $id, int $amount): void {
                (new PdoTransactionManager(self::connection()))->transactional(static function () use ($id, $amount): void {
                    $tenant = self::tenantContext()->getTenantId();
                    $invoice = self::createInvoicePort()->findPayableByPublicIdForUpdate($tenant, $id);
                    if ($invoice === null || !$invoice->isPayable()
                        || $invoice->getGrossTotal()->getCurrency()->getCode() !== 'EUR'
                        || $invoice->getGrossTotal()->getMinorUnits() !== $amount
                        || (new PdoPaymentRepository(self::connection()))->getAllocatedTotal(
                            $tenant, 'invoice', $id, $invoice->getGrossTotal()->getCurrency())->getMinorUnits() !== 0) {
                        throw new \DomainException('Het openstaande factuurbedrag is gewijzigd. Controleer de betalingen vóór incasso.');
                    }
                });
            });
    }

    public static function collectionPdfData(\Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice $invoice): ?array
    {
        if (!self::directDebitReady()) { return null; }
        $service = self::createDirectDebit();
        $debit = $service->findDebit($invoice->getPublicId());
        if ($debit !== null && in_array($debit['status'], ['awaiting_notice', 'scheduled', 'creating', 'pending', 'paid', 'attention'], true)) {
            return ['collection_notice' => (string)$debit['notice']];
        }
        if ($debit === null && DirectDebitSettings::enabled()) {
            $customer = $service->customer($invoice->getCustomerSnapshot()->getSourcePublicId());
            if ($customer !== null && !empty($customer['enabled'])) {
                return ['collection_notice' => 'Deze factuur wordt automatisch geïncasseerd. De incassodatum wordt per e-mail aangekondigd. U hoeft niet zelf over te maken.'];
            }
        }
        return null;
    }

    private static function directDebitWebhookBase(): string
    {
        $url = defined('__ADMIN_INVOICE_MOLLIE_WEBHOOK_BASE_URL__')
            ? trim((string)__ADMIN_INVOICE_MOLLIE_WEBHOOK_BASE_URL__)
            : (defined('__DOMAIN__') ? rtrim((string)__DOMAIN__, '/') . '/api/AdminInvoiceDirectDebit' : '');
        return preg_match('~^https://[^\s]+$~iD', $url) ? rtrim($url, '/') : '';
    }

    private static function directDebitKey(): string
    {
        // Een expliciete factuurkey mag een testkey zijn; de bestaande algemene key alleen live.
        if (defined('__ADMIN_INVOICE_MOLLIE_KEY__')) { return trim((string)__ADMIN_INVOICE_MOLLIE_KEY__); }
        return defined('__MOLLIE_KEY__') && strpos((string)__MOLLIE_KEY__, 'live_') === 0 ? trim((string)__MOLLIE_KEY__) : '';
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
