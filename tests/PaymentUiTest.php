<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/flexgrid/src/Html/Table/TrustedHtml.php';
require_once dirname(__DIR__, 3) . '/flexgrid/src/Html/Table/TableRenderer.php';

use Flexgrid\Html\Table\TableRenderer;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;
use Flexgrid\Modules\AdminPayment\Application\ReadModel\PaymentAllocationView;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\BookingDate;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\PaymentType;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\InvoicePaymentPresenter;
use Flexgrid\Utils\_Time;

$currency = Currency::euro();
$context = new InvoiceDetailContext(
    'invoice-ui-test',
    '2026-0042',
    InvoiceStatus::FINALIZED,
    PaymentStatus::PARTIALLY_PAID,
    new Money(12100, $currency)
);
$presenter = new InvoicePaymentPresenter(new _Time(strtotime('2026-09-18 12:00:00 UTC')));
$viewModel = $presenter->present($context, [
    new PaymentAllocationView(
        'payment-ui-1',
        'allocation-ui-1',
        new PaymentType(PaymentType::RECEIPT),
        new Money(5000, $currency),
        new BookingDate('2026-09-17'),
        '<Eerste betaling>',
        '<BANK-1>',
        'bank_import'
    ),
    new PaymentAllocationView(
        'payment-ui-2',
        'allocation-ui-2',
        new PaymentType(PaymentType::REFUND),
        new Money(-1000, $currency),
        new BookingDate('2026-09-18'),
        'Terugbetaling',
        '',
        'manual'
    ),
], 'payment-register-event', 'payment-update-event', 'payment-delete-event');

adminPaymentAssert($viewModel['allocatedTotal'] === '€ 40,00', 'Presenter moet receipts en refunds signed sommeren.');
adminPaymentAssert($viewModel['balance'] === '€ 81,00', 'Presenter moet het openstaande bedrag server-side bepalen.');
adminPaymentAssert($viewModel['suggestedAmount'] === '81.00', 'Formulier moet het actuele openstaande bedrag voorstellen.');
adminPaymentAssert($viewModel['bookedOn'] === '2026-09-18', 'Boekdatum moet via de gedeelde klok worden bepaald.');

$tableHtml = (new TableRenderer($viewModel['table']))->render();
adminPaymentAssert(strpos($tableHtml, 'data-fg-table="admin-invoice-payments"') !== false, 'Betaalhistorie moet de gedeelde TableRenderer gebruiken.');
adminPaymentAssert(strpos($tableHtml, '&lt;Eerste betaling&gt;') !== false, 'Paymentomschrijving moet escaped worden.');
adminPaymentAssert(strpos($tableHtml, '<BANK-1>') === false && strpos($tableHtml, '&lt;BANK-1&gt;') !== false, 'Paymentreferentie moet escaped worden.');
adminPaymentAssert(strpos($tableHtml, 'Terugbetaling') !== false && strpos($tableHtml, 'value="10.00"') !== false, 'Refund moet herkenbaar en met positief invoerbedrag bewerkbaar zijn.');
adminPaymentAssert(substr_count($tableHtml, 'payment-update-event') === 1
    && substr_count($tableHtml, 'payment-delete-event') === 1
    && strpos($tableHtml, 'data-allocation_public_id="allocation-ui-2"') !== false,
    'Alleen de handmatige betaling mag inline worden aangepast of verwijderd.');

$template = (string)file_get_contents(dirname(__DIR__) . '/src/Templates/Invoice/Panel.php');
adminPaymentAssert(strpos($template, 'class="admin-form admin-payment-form"') !== false, 'Paymentformulier moet de generieke form-gridreset gebruiken.');
adminPaymentAssert(strpos($template, 'ajax="true"') !== false, 'Paymentformulier moet declaratieve Flexgrid-AJAX gebruiken.');
adminPaymentAssert(strpos($template, 'name="payment_type"') !== false, 'Paymentformulier moet ontvangst en terugbetaling kunnen onderscheiden.');
adminPaymentAssert(strpos($template, 'new TableRenderer') !== false, 'Paymenttemplate moet de generieke TableRenderer gebruiken.');
adminPaymentAssert(strpos($template, 'Mollie-betaallink') !== false
    && strpos($template, 'createLinkAction') !== false,
    'Betaallink moet optioneel en expliciet vanuit het betaalpaneel worden aangemaakt.');

$integrationSource = '';
foreach (glob(dirname(__DIR__) . '/src/Integration/Invoice/*.php') ?: [] as $file) {
    $integrationSource .= (string)file_get_contents($file);
}
adminPaymentAssert(stripos($integrationSource, 'jquery') === false, 'Paymentintegratie mag geen jQuery toevoegen.');
adminPaymentAssert(stripos($integrationSource, 'fetch(') === false, 'Paymentintegratie moet Flexgrids centrale AJAX-transport gebruiken.');

echo "AdminPayment UI tests passed.\n";
