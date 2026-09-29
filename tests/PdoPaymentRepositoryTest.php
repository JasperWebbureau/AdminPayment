<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/AdminInvoice/src/Infrastructure/Persistence/PdoInvoicePaymentPort.php';

use Flexgrid\Modules\AdminCore\Context\TenantContext;
use Flexgrid\Modules\AdminCore\Infrastructure\PdoTransactionManager;
use Flexgrid\Modules\AdminCore\Infrastructure\UuidV4Generator;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoInvoicePaymentPort;
use Flexgrid\Modules\AdminPayment\Application\Command\RegisterInvoicePaymentCommand;
use Flexgrid\Modules\AdminPayment\Infrastructure\Persistence\PdoPaymentRepository;
use Flexgrid\Modules\AdminPayment\Integration\Invoice\RegisterInvoicePayment;
use Flexgrid\Utils\_Time;

$projectRoot = dirname(__DIR__, 4);
require $projectRoot . '/.env.php';
$connection = new PDO(
    'mysql:host=' . $db['host'] . ';dbname=' . $db['name'],
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
unset($db);

$tenantId = new TenantId('integration-admin-payment');
$invoicePublicId = 'payment-invoice-fixture';
$connection->beginTransaction();
try {
    $insertInvoice = $connection->prepare(
        'INSERT INTO `admin_invoice` ('
        . '`tenant_id`, `public_id`, `invoice_number`, `invoice_sequence_value`, `status`, `payment_status`, `currency`, '
        . '`customer_snapshot`, `billing_address_snapshot`, `issue_date`, `due_date`, `finalized_at`, '
        . '`net_total_minor`, `tax_total_minor`, `gross_total_minor`, `tax_summary_snapshot`, `created_at`, `updated_at`'
        . ') VALUES ('
        . ':tenant_id, :public_id, :invoice_number, 1, \'final\', \'unpaid\', \'EUR\', '
        . ':customer, :address, \'2026-09-01\', \'2026-10-01\', 1770000000, '
        . '10000, 2100, 12100, :tax_summary, 1770000000, 1770000000'
        . ')'
    );
    $insertInvoice->execute([
        ':tenant_id' => $tenantId->toString(),
        ':public_id' => $invoicePublicId,
        ':invoice_number' => '2026-PAY-0001',
        ':customer' => json_encode(['schema_version' => 1, 'name' => 'Betaalklant']),
        ':address' => json_encode(['schema_version' => 1, 'line_1' => 'Straat 1', 'postal_code' => '1000 AA', 'city' => 'Utrecht', 'country_code' => 'NL']),
        ':tax_summary' => json_encode(['schema_version' => 1, 'groups' => []]),
    ]);

    $repository = new PdoPaymentRepository($connection);
    $useCase = new RegisterInvoicePayment(
        new TenantContext($tenantId),
        new UuidV4Generator(),
        new PdoTransactionManager($connection),
        $repository,
        new PdoInvoicePaymentPort($connection),
        new _Time(strtotime('2026-09-18 12:00:00 UTC'))
    );

    $first = $useCase->execute(new RegisterInvoicePaymentCommand(
        $invoicePublicId, '40.00', 'EUR', '2026-09-18', 'receipt', '', 'BANK-PDO-1', 'bank_import', 'pdo-ext-1'
    ));
    $reloaded = $repository->findByExternalReference($tenantId, 'bank_import', 'pdo-ext-1');
    adminPaymentAssert($reloaded !== null && $reloaded->getPublicId() === $first->getPublicId(), 'PDO-repository moet payment tenantgebonden hydrateren.');
    adminPaymentAssert(count($reloaded->getAllocations()) === 1 && $reloaded->isFullyAllocated(), 'PDO-repository moet allocatie hydrateren.');
    adminPaymentAssert($repository->findByExternalReference(new TenantId('other-payment-tenant'), 'bank_import', 'pdo-ext-1') === null, 'Andere tenant mag payment niet lezen.');

    $status = $connection->prepare('SELECT `payment_status` FROM `admin_invoice` WHERE `tenant_id` = :tenant_id AND `public_id` = :public_id');
    $status->execute([':tenant_id' => $tenantId->toString(), ':public_id' => $invoicePublicId]);
    adminPaymentAssert($status->fetchColumn() === 'partially_paid', 'Eerste PDO-betaling moet partially_paid projecteren.');

    $useCase->execute(new RegisterInvoicePaymentCommand(
        $invoicePublicId, '81.00', 'EUR', '2026-09-19', 'receipt', '', 'BANK-PDO-2', 'bank_import', 'pdo-ext-2'
    ));
    $status->execute([':tenant_id' => $tenantId->toString(), ':public_id' => $invoicePublicId]);
    adminPaymentAssert($status->fetchColumn() === 'paid', 'Meerdere PDO-betalingen moeten paid projecteren.');

    $allocations = $repository->listAllocationsForTarget($tenantId, 'invoice', $invoicePublicId, Currency::euro());
    adminPaymentAssert(count($allocations) === 2, 'PDO-repository moet de volledige betaalhistorie voor het target lezen.');
    adminPaymentAssert($allocations[0]->getBookedOn()->getValue() === '2026-09-19', 'Betaalhistorie moet nieuwste boekdatum eerst tonen.');
    adminPaymentAssert($allocations[1]->getReference() === 'BANK-PDO-1', 'Betaalhistorie moet de opgeslagen referentie hydrateren.');
    adminPaymentAssert(
        $repository->listAllocationsForTarget(new TenantId('other-payment-tenant'), 'invoice', $invoicePublicId, Currency::euro()) === [],
        'Andere tenant mag de betaalhistorie niet lezen.'
    );

    $detail = (new PdoInvoicePaymentPort($connection))->findPaymentDetailByPublicId($tenantId, $invoicePublicId);
    adminPaymentAssert($detail !== null && $detail->getPaymentStatus() === 'paid', 'Invoice-betaalpoort moet de actuele detailstatus teruggeven.');

    $retry = $useCase->execute(new RegisterInvoicePaymentCommand(
        $invoicePublicId, '40.00', 'EUR', '2026-09-18', 'receipt', '', 'BANK-PDO-1', 'bank_import', 'pdo-ext-1'
    ));
    adminPaymentAssert($retry->getPublicId() === $first->getPublicId(), 'PDO-retry moet idempotent hetzelfde payment teruggeven.');
    $count = $connection->prepare('SELECT COUNT(*) FROM `admin_payment` WHERE `tenant_id` = :tenant_id');
    $count->execute([':tenant_id' => $tenantId->toString()]);
    adminPaymentAssert((int)$count->fetchColumn() === 2, 'Idempotente retry mag geen derde payment maken.');

    $connection->rollBack();
} catch (Throwable $throwable) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    throw $throwable;
}

foreach (['admin_payment', 'admin_payment_allocation', 'admin_invoice'] as $table) {
    $cleanup = $connection->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `tenant_id` = :tenant_id');
    $cleanup->execute([':tenant_id' => $tenantId->toString()]);
    adminPaymentAssert((int)$cleanup->fetchColumn() === 0, 'Integratietest liet fixtures achter in ' . $table . '.');
}

echo "AdminPayment PDO repository tests passed.\n";
