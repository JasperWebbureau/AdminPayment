<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminCore\Infrastructure\PdoTransactionManager;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Infrastructure\Persistence\PdoInvoicePaymentPort;
use Flexgrid\Modules\AdminPayment\Infrastructure\Persistence\PdoPaymentRepository;

/** Mutations are limited to a single, fully allocated manual payment on this invoice. */
final class ManualInvoicePaymentEditor
{
    private $connection;
    private $tenant;

    public function __construct(\PDO $connection, TenantId $tenant)
    {
        $this->connection = $connection;
        $this->tenant = $tenant;
    }

    public function change(string $invoiceId, string $allocationId, ?string $decimalAmount): void
    {
        $tenant = $this->tenant;
        (new PdoTransactionManager($this->connection))->transactional(function () use ($tenant, $invoiceId, $allocationId, $decimalAmount): void {
            $invoices = new PdoInvoicePaymentPort($this->connection);
            $invoice = $invoices->findPayableByPublicIdForUpdate($tenant, $invoiceId);
            if ($invoice === null || $invoice->getStatus() !== InvoiceStatus::FINALIZED) {
                throw new \DomainException('Alleen betalingen van een definitieve factuur kunnen worden aangepast.');
            }
            $statement = $this->connection->prepare(
                "SELECT p.`id`,p.`payment_type`,p.`amount_minor`,p.`currency`,a.`id` allocation_id,a.`amount_minor` allocation_minor "
                . "FROM `admin_payment_allocation` a INNER JOIN `admin_payment` p ON p.`id`=a.`payment_id` AND p.`tenant_id`=a.`tenant_id` "
                . "WHERE a.`tenant_id`=:tenant AND a.`public_id`=:allocation AND a.`target_type`='invoice' "
                . "AND a.`target_public_id`=:invoice AND p.`source`='manual' FOR UPDATE"
            );
            $statement->execute([':tenant' => $tenant->toString(), ':allocation' => $allocationId, ':invoice' => $invoiceId]);
            $matches = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $payment = $matches[0] ?? null;
            if (!is_array($payment) || $payment['currency'] !== $invoice->getGrossTotal()->getCurrency()->getCode()
                || (int)$payment['amount_minor'] !== (int)$payment['allocation_minor']) {
                throw new \DomainException('Deze handmatige betaling kan niet worden aangepast.');
            }
            $count = $this->connection->prepare('SELECT COUNT(*) FROM `admin_payment_allocation` WHERE `tenant_id`=:tenant AND `payment_id`=:payment');
            $count->execute([':tenant' => $tenant->toString(), ':payment' => $payment['id']]);
            if ((int)$count->fetchColumn() !== 1) {
                throw new \DomainException('Een betaling met meerdere allocaties kan hier niet worden aangepast.');
            }
            if ($decimalAmount === null) {
                $deleteAllocation = $this->connection->prepare('DELETE FROM `admin_payment_allocation` WHERE `tenant_id`=:tenant AND `id`=:id');
                $deleteAllocation->execute([':tenant' => $tenant->toString(), ':id' => $payment['allocation_id']]);
                $deletePayment = $this->connection->prepare("DELETE FROM `admin_payment` WHERE `tenant_id`=:tenant AND `id`=:id AND `source`='manual'");
                $deletePayment->execute([':tenant' => $tenant->toString(), ':id' => $payment['id']]);
            } else {
                $amount = Money::fromDecimal($decimalAmount, $invoice->getGrossTotal()->getCurrency())->getMinorUnits();
                if ($amount <= 0) { throw new \InvalidArgumentException('Vul een positief bedrag in.'); }
                if ($payment['payment_type'] === 'refund') { $amount = -$amount; }
                $updatePayment = $this->connection->prepare("UPDATE `admin_payment` SET `amount_minor`=:amount,`updated_at`=:updated WHERE `tenant_id`=:tenant AND `id`=:id AND `source`='manual'");
                $updatePayment->execute([':amount' => $amount, ':updated' => time(), ':tenant' => $tenant->toString(), ':id' => $payment['id']]);
                $updateAllocation = $this->connection->prepare('UPDATE `admin_payment_allocation` SET `amount_minor`=:amount WHERE `tenant_id`=:tenant AND `id`=:id');
                $updateAllocation->execute([':amount' => $amount, ':tenant' => $tenant->toString(), ':id' => $payment['allocation_id']]);
            }
            $total = (new PdoPaymentRepository($this->connection))
                ->getAllocatedTotal($tenant, 'invoice', $invoiceId, $invoice->getGrossTotal()->getCurrency());
            if ($total->getMinorUnits() < 0) {
                throw new \DomainException('Terugbetalingen mogen het ontvangen bedrag niet overschrijden.');
            }
            $invoices->synchronizePaymentStatus($invoice, $total, time());
        });
    }
}
