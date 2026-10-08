<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Event\AjaxEvent;
use Flexgrid\Modules\AdminCore\Service\AdminHeader;
use Flexgrid\Modules\AdminInvoice\Service\AdminInvoiceFactory;
use Flexgrid\Modules\AdminCustomer\Service\AdminCustomerFactory;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\DirectDebitSettings;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\InvoicePaymentFactory;
use Flexgrid\Response\AjaxResponse;
use Flexgrid\Response\PageResponse;
use Flexgrid\Response\TemplateResponse;
use Flexgrid\Utils\Request\Request;

/**
 * @FG\Controller [name=AdminInvoiceCollection,type=Module,icon=fas fa-building-columns,level=5,administrationPanel=true,administrationLabel=Factuurincasso,administrationRoute=index,administrationPriority=45]
 */
final class AdminInvoiceCollectionController
{
    public function index()
    {
        appendIconAndTitleToHeader('fas fa-building-columns', 'Factuurincasso', 'Administratie');
        PageResponse::addAsset('Flexgrid/Flexgrid/src/Html/Admin/Css/AdminUi.scss');
        AdminHeader::AdminAddHeader([
            ['href' => '/Flexgrid/AdminInvoiceProcessing/index', 'label' => 'Factuurverwerking'],
            ['href' => '/Flexgrid/AdminCustomer/customers', 'label' => 'Klanten'],
        ]);
        $ready = InvoicePaymentFactory::directDebitReady();
        $rows = $ready ? InvoicePaymentFactory::createDirectDebit()->overview() : [];
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = ['id' => $row['invoice_public_id'], 'url' => '/Flexgrid/AdminInvoice/detail/' . rawurlencode($row['invoice_public_id']),
                'cells' => ['number' => $row['invoice_number'], 'customer' => $row['customer_name'],
                    'date' => $row['collect_on'], 'amount' => 'EUR ' . (new \Flexgrid\Modules\AdminCore\ValueObject\Money((int)$row['amount_minor'], \Flexgrid\Modules\AdminCore\ValueObject\Currency::euro()))->format(),
                    'status' => self::statusLabel($row['status']), 'mode' => $row['mode'] === 'test' ? 'Test' : 'Live',
                    'error' => $row['error'] ?? '']];
        }
        return new TemplateResponse('Flexgrid/Modules/AdminPayment/src/Templates/DirectDebit/Index.php', [
            'ready' => $ready, 'configured' => InvoicePaymentFactory::directDebitConfigured(),
            'enabled' => DirectDebitSettings::enabled(), 'days' => DirectDebitSettings::noticeDays(),
            'actions' => ['save' => self::action('save'), 'run' => self::action('run')],
            'table' => ['id' => 'invoice-direct-debits', 'label' => 'Geplande en uitgevoerde incasso’s',
                'columns' => [['key' => 'number', 'label' => 'Factuur'], ['key' => 'customer', 'label' => 'Klant'],
                    ['key' => 'date', 'label' => 'Incasso vanaf'], ['key' => 'amount', 'label' => 'Bedrag'],
                    ['key' => 'status', 'label' => 'Status'], ['key' => 'mode', 'label' => 'Modus'], ['key' => 'error', 'label' => 'Aandacht']],
                'rows' => $tableRows, 'empty' => ['title' => 'Nog geen incasso’s', 'message' => 'Regel eerst de klantmachtiging. Een verstuurde, goedgekeurde factuur kan daarna worden geïncasseerd.']],
        ]);
    }

    public static function action(string $method): string
    {
        $event = new AjaxEvent(self::class, $method); $event->setMinimumAccessLevel(5); return $event->getName();
    }

    public static function statusLabel(string $status): string
    {
        return ['awaiting_notice' => 'Aankondiging nog versturen', 'scheduled' => 'Gepland', 'creating' => 'Aanmaak in verwerking',
            'pending' => 'Bank verwerkt incasso', 'paid' => 'Ontvangen', 'failed' => 'Mislukt', 'canceled' => 'Afgebroken',
            'expired' => 'Verlopen', 'chargeback' => 'Teruggeboekt', 'attention' => 'Controle nodig', 'stopped' => 'Gestopt'][$status] ?? 'Controle nodig';
    }

    public function save(): AjaxResponse
    {
        return $this->respond(function (): void {
            $request = new Request();
            if ($request->getBoolean('enabled') && !InvoicePaymentFactory::directDebitReady()) {
                throw new \LogicException('Rond eerst de Mollie-configuratie en installatie af.');
            }
            DirectDebitSettings::save($request->getBoolean('enabled'), (int)$request->getString('notice_days'));
        }, '/Flexgrid/AdminInvoiceCollection/index');
    }

    public function setup(): AjaxResponse
    {
        return $this->customerAction(function ($customer): void {
            if ($customer->getStatus()->getValue() !== 'active') { throw new \DomainException('Activeer de klant voordat je een machtiging aanvraagt.'); }
            $contact = $customer->getPrimaryContact();
            InvoicePaymentFactory::createDirectDebit()->createSetup($customer->getPublicId(), $customer->getDisplayName(),
                $contact !== null ? $contact->getEmail() : '', InvoicePaymentFactory::mollieRedirectUrl());
        });
    }

    public function syncCustomer(): AjaxResponse
    {
        return $this->customerAction(function ($customer): void {
            InvoicePaymentFactory::createDirectDebit()->syncSetup($customer->getPublicId());
        });
    }

    public function customerEnabled(): AjaxResponse
    {
        return $this->customerAction(function ($customer): void {
            InvoicePaymentFactory::createDirectDebit()->setCustomerEnabled($customer->getPublicId(), (new Request())->getBoolean('enabled'));
        });
    }

    private function customerAction(callable $operation): AjaxResponse
    {
        $id = (new Request())->getString('customer_public_id');
        return $this->respond(function () use ($id, $operation): void {
            $operation(AdminCustomerFactory::createGetCustomer()->execute($id));
        }, '/Flexgrid/AdminCustomer/edit/' . rawurlencode($id));
    }

    public function announce(): AjaxResponse
    {
        $id = (new Request())->getString('invoice_public_id');
        return $this->respond(function () use ($id): void {
            $invoice = AdminInvoiceFactory::createGetInvoice()->execute($id);
            $service = InvoicePaymentFactory::createDirectDebit();
            $notice = $service->prepare($invoice);
            if ($notice === '') { throw new \DomainException('Geen nieuwe incasso om aan te kondigen. Controleer de machtiging, klantvoorkeur en planning.'); }
            $history = AdminInvoiceFactory::createDispatchService()->history($id);
            AdminInvoiceFactory::createDispatchService()->send($invoice, 'invoice',
                (string)$invoice->getCustomerSnapshot()->toArray()['email'], $history !== [], $notice);
            $service->noticeSent($id);
            if (AdminInvoiceFactory::processingReady()) { AdminInvoiceFactory::createProcessing()->completeAfterDispatch($id); }
        }, '/Flexgrid/AdminInvoice/detail/' . rawurlencode($id));
    }

    public function syncInvoice(): AjaxResponse
    {
        $id = (new Request())->getString('invoice_public_id');
        return $this->respond(function () use ($id): void { InvoicePaymentFactory::createDirectDebit()->synchronize($id); },
            '/Flexgrid/AdminInvoice/detail/' . rawurlencode($id));
    }

    public function recoverInvoice(): AjaxResponse
    {
        $request = new Request(); $id = $request->getString('invoice_public_id');
        return $this->respond(function () use ($request, $id): void {
            InvoicePaymentFactory::createDirectDebit()->recoverPayment($id, $request->getString('payment_id'));
        }, '/Flexgrid/AdminInvoice/detail/' . rawurlencode($id));
    }

    public function run(): AjaxResponse
    {
        return $this->respond(function (): void {
            $service = InvoicePaymentFactory::createDirectDebit();
            $checked = $service->synchronizeOpen();
            $started = $service->runDue(date('Y-m-d'));
            if ($checked['failed'] > 0 || $started['failed'] > 0) { throw new \DomainException('Een of meer incasso’s vereisen aandacht. Controleer de status in het overzicht.'); }
        }, '/Flexgrid/AdminInvoiceCollection/index');
    }

    private function respond(callable $operation, string $redirect): AjaxResponse
    {
        $response = new AjaxResponse();
        try { $operation(); $response->success = true; $response->redirect = $redirect; }
        catch (\Throwable $exception) {
            $response->success = false;
            $message = $exception instanceof \DomainException || $exception instanceof \LogicException
                || $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'De actie is niet afgerond. Controleer de status voordat je opnieuw probeert.';
            $response->notifications = ['<div class="notification notification--error">' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>'];
        }
        return $response;
    }
}
