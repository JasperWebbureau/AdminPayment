<?php
declare(strict_types=1);
namespace Flexgrid\Modules\AdminPayment\Integration\Flexgrid\Controller;

use Flexgrid\Auth\Auth;
use Flexgrid\Database\Connection;
use Flexgrid\Form\Form;
use Flexgrid\Form\Input\SelectInput;
use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminPayment\Application\UseCase\GetPaymentOverview;
use Flexgrid\Modules\AdminPayment\Infrastructure\Persistence\PdoPaymentRepository;
use Flexgrid\Response\TemplateResponse;
use Flexgrid\Utils\Request\Request;

/** @FG\Controller[name=AdminPayments,type=Module,icon=fas fa-money-bill-transfer,level=5,administrationPanel=true,administrationLabel=Betalingen,administrationRoute=index,administrationPriority=44] */
final class PaymentOverviewController
{
    public function index()
    {
        if (!Auth::get('Flexgrid')->hasAccess(5)) { http_response_code(403); return ''; }
        if (!defined('__ADMIN_TENANT_ID__') || trim((string)__ADMIN_TENANT_ID__) === '') { throw new \LogicException('Stel een vaste administratie-tenant in.'); }
        $month = (new Request())->getString('month') ?: date('Y-m');
        $query = new GetPaymentOverview(new TenantContext(new TenantId((string)__ADMIN_TENANT_ID__)), new PdoPaymentRepository(Connection::getConnections()));
        try { $overview = $query->execute($month); }
        catch (\InvalidArgumentException $exception) { $month = date('Y-m'); $overview = $query->execute($month); }
        $rows = [];
        foreach ($overview['payments'] as $payment) {
            $rows[] = ['id' => $payment['public_id'], 'cells' => ['date' => $payment['booked_on'],
                'description' => ['value' => $payment['description'], 'secondary' => $payment['reference'], 'title' => true],
                'source' => $payment['source'], 'type' => $payment['payment_type'] === 'refund' ? 'Terugbetaling' : 'Ontvangst',
                'amount' => (new Money((int)$payment['amount_minor'], new Currency($payment['currency'])))->format(',', '.') . ' ' . $payment['currency']]];
        }
        $table = ['id' => 'admin-payment-overview', 'label' => 'Recente betalingen in ' . $month . ' (maximaal 100)', 'columns' => [
            ['key' => 'date', 'label' => 'Boekdatum'], ['key' => 'description', 'label' => 'Omschrijving'], ['key' => 'source', 'label' => 'Bron'], ['key' => 'type', 'label' => 'Type'], ['key' => 'amount', 'label' => 'Bedrag'],
        ], 'rows' => $rows];
        $totals = [];
        foreach ($overview['totals'] as $total) {
            $currency = new Currency($total['currency']);
            $totals[] = ['currency' => $currency->getCode(), 'receipts' => (new Money((int)$total['receipts_minor'], $currency))->format(',', '.'),
                'refunds' => (new Money((int)$total['refunds_minor'], $currency))->format(',', '.'), 'net' => (new Money((int)$total['net_minor'], $currency))->format(',', '.')];
        }
        $filter = new Form('PaymentOverviewFilter');
        $filter->setMethod('get');
        $select = new SelectInput('month');
        $select->setLabel('Maand');
        $options = [$month => $month];
        for ($i = 0; $i < 24; $i++) { $value = (new \DateTimeImmutable('first day of this month'))->modify('-' . $i . ' months')->format('Y-m'); $options[$value] = $value; }
        krsort($options);
        $select->setOptions($options);
        $select->setValue($month);
        $filter->addInput($select);
        $filter->addInput('<div><button class="button" type="submit">Bekijken</button></div>');
        return new TemplateResponse('Flexgrid/Modules/AdminPayment/src/Templates/Overview/Overview.php', compact('filter', 'table', 'totals', 'month'));
    }
}
