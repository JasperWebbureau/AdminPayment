<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

$projectRoot = dirname(__DIR__, 4);
require $projectRoot . '/.env.php';
$connection = new PDO(
    'mysql:host=' . $db['host'] . ';dbname=' . $db['name'],
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
unset($db);

function adminPaymentSchemaColumns(PDO $connection, string $table): array
{
    $columns = [];
    foreach ($connection->query('SHOW FULL COLUMNS FROM `' . $table . '`')->fetchAll() as $row) {
        $columns[$row['Field']] = $row;
    }
    return $columns;
}

function adminPaymentSchemaIndex(PDO $connection, string $table, string $index): array
{
    $statement = $connection->prepare(
        'SELECT `NON_UNIQUE` AS `Non_unique`, `SEQ_IN_INDEX` AS `Seq_in_index`, `COLUMN_NAME` AS `Column_name` '
        . 'FROM information_schema.statistics '
        . 'WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index ORDER BY `Seq_in_index`'
    );
    $statement->execute([':table' => $table, ':index' => $index]);
    return $statement->fetchAll();
}

$payment = adminPaymentSchemaColumns($connection, 'admin_payment');
foreach ([
    'tenant_id' => 'varchar(64)',
    'public_id' => 'varchar(64)',
    'payment_type' => 'varchar(16)',
    'amount_minor' => 'bigint',
    'currency' => 'varchar(3)',
    'booked_on' => 'varchar(10)',
    'source' => 'varchar(64)',
    'external_id' => 'varchar(128)',
] as $column => $type) {
    adminPaymentAssert(isset($payment[$column]), 'Databasekolom admin_payment.' . $column . ' ontbreekt.');
    adminPaymentAssert(strpos(strtolower((string)$payment[$column]['Type']), $type) === 0, 'Onverwacht type voor admin_payment.' . $column . '.');
}
foreach (['tenant_id', 'public_id', 'payment_type', 'amount_minor', 'currency', 'booked_on', 'source'] as $column) {
    adminPaymentAssert($payment[$column]['Null'] === 'NO', 'Databasekolom admin_payment.' . $column . ' moet NOT NULL zijn.');
}
adminPaymentAssert($payment['external_id']['Null'] === 'YES', 'Externe id moet SQL NULL toestaan voor handmatige betalingen.');

$allocation = adminPaymentSchemaColumns($connection, 'admin_payment_allocation');
foreach ([
    'tenant_id' => 'varchar(64)',
    'public_id' => 'varchar(64)',
    'payment_id' => 'int',
    'target_type' => 'varchar(32)',
    'target_public_id' => 'varchar(64)',
    'amount_minor' => 'bigint',
    'allocated_at' => 'bigint',
] as $column => $type) {
    adminPaymentAssert(isset($allocation[$column]), 'Databasekolom admin_payment_allocation.' . $column . ' ontbreekt.');
    adminPaymentAssert(strpos(strtolower((string)$allocation[$column]['Type']), $type) === 0, 'Onverwacht type voor admin_payment_allocation.' . $column . '.');
    adminPaymentAssert($allocation[$column]['Null'] === 'NO', 'Databasekolom admin_payment_allocation.' . $column . ' moet NOT NULL zijn.');
}
$link = adminPaymentSchemaColumns($connection, 'admin_invoice_payment_link');
foreach (['tenant_id', 'invoice_public_id', 'status', 'provider_link_id', 'url', 'amount_minor', 'currency', 'payment_id'] as $column) {
    adminPaymentAssert(isset($link[$column]), 'Factuurbetaallinkkolom ' . $column . ' ontbreekt.');
}

foreach ([
    ['admin_payment', 'tenant_public', ['tenant_id', 'public_id'], true],
    ['admin_payment', 'tenant_source_external', ['tenant_id', 'source', 'external_id'], true],
    ['admin_invoice_payment_link', 'tenant_invoice', ['tenant_id', 'invoice_public_id'], true],
    ['admin_invoice_payment_link', 'provider_link', ['provider_link_id'], true],
    ['admin_payment_allocation', 'tenant_public', ['tenant_id', 'public_id'], true],
    ['admin_payment_allocation', 'tenant_payment_target', ['tenant_id', 'payment_id', 'target_type', 'target_public_id'], true],
    ['admin_payment_allocation', 'tenant_target', ['tenant_id', 'target_type', 'target_public_id'], false],
] as $expected) {
    list($table, $name, $columns, $unique) = $expected;
    $rows = adminPaymentSchemaIndex($connection, $table, $name);
    adminPaymentAssert(array_column($rows, 'Column_name') === $columns, 'Index ' . $table . '.' . $name . ' heeft een onverwachte kolomvolgorde.');
    foreach ($rows as $row) {
        adminPaymentAssert((int)$row['Non_unique'] === ($unique ? 0 : 1), 'Index ' . $table . '.' . $name . ' heeft onverwachte uniqueness.');
    }
}

echo "AdminPayment database schema tests passed.\n";
