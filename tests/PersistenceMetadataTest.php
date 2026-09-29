<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$projectRoot = dirname(__DIR__, 4);
$frameworkSource = $projectRoot . '/flexgrid/flexgrid/src';

if (!class_exists('Repository\\RepositoryEntity')) {
    eval('namespace Repository; class RepositoryEntity {}');
}

require_once $frameworkSource . '/Utils/_Enum.php';
require_once $frameworkSource . '/Utils/_String.php';
require_once $frameworkSource . '/Database/Column/DefaultColumnLenth.php';
require_once $frameworkSource . '/Database/Column/ColumnType.php';
require_once $frameworkSource . '/Database/Column/Column.php';
require_once $frameworkSource . '/Autowire/Definition/PropertyDefinition.php';
require_once $frameworkSource . '/Autowire/Definition/EntityDefinition.php';
require_once $frameworkSource . '/Autowire/Scanner/EntityScanner.php';
require_once $frameworkSource . '/Autowire/Schema/SchemaIndexDefinition.php';
require_once dirname(__DIR__) . '/src/Entity/PaymentRecord.php';
require_once dirname(__DIR__) . '/src/Entity/PaymentAllocationRecord.php';
require_once dirname(__DIR__) . '/src/Entity/InvoicePaymentLinkRecord.php';

use Flexgrid\Autowire\Scanner\EntityScanner;
use Flexgrid\Autowire\Schema\SchemaIndexDefinition;
use Flexgrid\Modules\AdminPayment\Entity\PaymentAllocationRecord;
use Flexgrid\Modules\AdminPayment\Entity\PaymentRecord;
use Flexgrid\Modules\AdminPayment\Entity\InvoicePaymentLinkRecord;

$scanner = new EntityScanner();
$paymentFile = dirname(__DIR__) . '/src/Entity/PaymentRecord.php';
$allocationFile = dirname(__DIR__) . '/src/Entity/PaymentAllocationRecord.php';
$payment = $scanner->parseEntity(PaymentRecord::class, $paymentFile);
$allocation = $scanner->parseEntity(PaymentAllocationRecord::class, $allocationFile);
$link = $scanner->parseEntity(
    InvoicePaymentLinkRecord::class, dirname(__DIR__) . '/src/Entity/InvoicePaymentLinkRecord.php'
);
adminPaymentAssert($link->getTableName() === 'admin_invoice_payment_link', 'Mollie-links moeten een eigen tabel hebben.');
$linkIndex = SchemaIndexDefinition::fromEntityAnnotation(
    $link, 'tenant_invoice', $link->getClassAnnotations()['Index']['tenant_invoice']
);
adminPaymentAssert(strpos($linkIndex->buildCreateSql('admin_invoice_payment_link'), 'UNIQUE INDEX') !== false,
    'Per factuur mag slechts een Mollie-link worden vastgelegd.');

adminPaymentAssert($payment->getTableName() === 'admin_payment', 'PaymentRecord moet zijn eigen moduletabel definiëren.');
adminPaymentAssert($allocation->getTableName() === 'admin_payment_allocation', 'PaymentAllocationRecord moet zijn eigen moduletabel definiëren.');

$paymentProperties = $payment->getProperties();
foreach (['tenantId', 'publicId', 'paymentType', 'amountMinor', 'currency', 'bookedOn', 'source', 'externalId'] as $property) {
    adminPaymentAssert(isset($paymentProperties[$property]), 'PaymentRecord mist property ' . $property . '.');
}
adminPaymentAssert($paymentProperties['amountMinor']->getDatabaseType() === 'BIGINT', 'Paymentbedrag moet signed BIGINT minor units gebruiken.');

$allocationProperties = $allocation->getProperties();
foreach (['tenantId', 'publicId', 'paymentId', 'targetType', 'targetPublicId', 'amountMinor', 'allocatedAt'] as $property) {
    adminPaymentAssert(isset($allocationProperties[$property]), 'PaymentAllocationRecord mist property ' . $property . '.');
}
adminPaymentAssert($allocationProperties['amountMinor']->getDatabaseType() === 'BIGINT', 'Allocatiebedrag moet signed BIGINT minor units gebruiken.');

$paymentAnnotations = $payment->getClassAnnotations();
$externalIndex = SchemaIndexDefinition::fromEntityAnnotation(
    $payment,
    'tenant_source_external',
    $paymentAnnotations['Index']['tenant_source_external']
);
adminPaymentAssert(strpos($externalIndex->buildCreateSql('admin_payment'), 'UNIQUE INDEX') !== false, 'Externe betaalreferentie moet per tenant en bron uniek zijn.');

$allocationAnnotations = $allocation->getClassAnnotations();
$targetIndex = SchemaIndexDefinition::fromEntityAnnotation(
    $allocation,
    'tenant_payment_target',
    $allocationAnnotations['Index']['tenant_payment_target']
);
adminPaymentAssert(strpos($targetIndex->buildCreateSql('admin_payment_allocation'), 'UNIQUE INDEX') !== false, 'Eén payment mag hetzelfde target niet dubbel alloceren.');

$sourceRoot = dirname(__DIR__) . '/src';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot));
foreach ($iterator as $sourceFile) {
    if (!$sourceFile->isFile() || strtolower($sourceFile->getExtension()) !== 'php') {
        continue;
    }
    $relative = str_replace('\\', '/', substr($sourceFile->getPathname(), strlen($sourceRoot)));
    $content = (string)file_get_contents($sourceFile->getPathname());
    adminPaymentAssert(strpos($content, 'App\\Administration\\') === false, 'AdminPayment mag de oude administratie niet importeren.');
    if (strpos($relative, '/Integration/') !== 0) {
        adminPaymentAssert(strpos($content, 'Flexgrid\\Modules\\AdminInvoice\\') === false, 'Alleen expliciete Payment-Integration mag Invoice kennen.');
    }
}

echo "AdminPayment persistence metadata tests passed.\n";
