<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminInvoice\Application\ReadModel\InvoiceDetailContext;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\PaymentStatus;

/** Optional consumer-owned payment-link integration; Invoice never imports Mollie. */
final class InvoiceMollieLinks
{
    private $connection;
    private $tenant;
    private $gateway;
    private $recordPayment;
    private $invoiceIsUnpaid;

    public function __construct(\PDO $connection, TenantId $tenant, PaymentLinkGatewayInterface $gateway, callable $recordPayment, ?callable $invoiceIsUnpaid = null)
    {
        $this->connection = $connection;
        $this->tenant = $tenant;
        $this->gateway = $gateway;
        $this->recordPayment = $recordPayment;
        $this->invoiceIsUnpaid = $invoiceIsUnpaid;
    }

    public function find(string $invoicePublicId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM `admin_invoice_payment_link` WHERE `tenant_id`=:tenant '
            . 'AND `invoice_public_id`=:invoice LIMIT 1'
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':invoice' => $invoicePublicId]);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $row = $rows[0] ?? false;
        return is_array($row) ? $row : null;
    }

    public function create(InvoiceDetailContext $invoice, string $webhookBaseUrl = '', string $redirectUrl = ''): array
    {
        if ($invoice->getStatus() !== InvoiceStatus::FINALIZED
            || $invoice->getPaymentStatus() !== PaymentStatus::UNPAID
            || $invoice->getGrossTotal()->getMinorUnits() <= 0) {
            throw new \DomainException('Een betaallink kan alleen voor een definitieve, nog onbetaalde factuur met positief bedrag.');
        }
        if ($this->find($invoice->getPublicId()) !== null) {
            throw new \DomainException('Voor deze factuur bestaat al een betaallink of een aanmaakpoging.');
        }
        if ($webhookBaseUrl !== '' && !preg_match('~^https://[^\s]+$~iD', $webhookBaseUrl)) {
            throw new \LogicException('De Mollie-webhook vereist een publieke HTTPS-URL.');
        }
        if ($redirectUrl !== '' && !preg_match('~^https://[^\s]+$~iD', $redirectUrl)) {
            throw new \LogicException('De Mollie-bedankpagina vereist een publieke HTTPS-URL.');
        }
        $amount = $invoice->getGrossTotal();
        $now = time();
        try {
            $insert = $this->connection->prepare(
                'INSERT INTO `admin_invoice_payment_link` '
                . '(`tenant_id`,`invoice_public_id`,`status`,`provider_link_id`,`url`,`amount_minor`,`currency`,`payment_id`,`error`,`created_at`,`updated_at`) '
                . 'VALUES (:tenant,:invoice,:status,NULL,NULL,:amount,:currency,NULL,NULL,:created,:updated)'
            );
            $insert->execute([
                ':tenant' => $this->tenant->toString(), ':invoice' => $invoice->getPublicId(),
                ':status' => 'creating', ':amount' => $amount->getMinorUnits(),
                ':currency' => $amount->getCurrency()->getCode(), ':created' => $now, ':updated' => $now,
            ]);
        } catch (\PDOException $exception) {
            throw new \DomainException('Voor deze factuur wordt al een betaallink aangemaakt.');
        }
        try {
            $link = $this->gateway->create(
                'Factuur ' . $invoice->getNumber(),
                $amount->getCurrency()->getCode(),
                $amount->format('.', ''),
                $webhookBaseUrl === '' ? '' : rtrim($webhookBaseUrl, '/') . '/' . rawurlencode($invoice->getPublicId()),
                $redirectUrl
            );
            if (($link['mode'] ?? '') !== 'live'
                || strpos((string)$link['id'], 'pl_') !== 0
                || !preg_match('~^https://[^\s]+$~iD', (string)$link['url'])) {
                throw new \UnexpectedValueException('Mollie gaf geen geldige betaallink terug.');
            }
            $this->update($invoice->getPublicId(), 'ready', (string)$link['id'], (string)$link['url'], '', '');
        } catch (\Throwable $exception) {
            $this->update($invoice->getPublicId(), 'failed', '', '', '', substr($exception->getMessage(), 0, 1000));
            throw $exception;
        }
        return $this->find($invoice->getPublicId());
    }

    /** Poll only existing, unpaid links; remote failures remain visible to the caller. */
    public function synchronizeOpenLinks(): array
    {
        $statement = $this->connection->prepare(
            'SELECT `invoice_public_id`,`provider_link_id` FROM `admin_invoice_payment_link` '
            . 'WHERE `tenant_id`=:tenant AND `status`=:status AND `provider_link_id` IS NOT NULL'
        );
        $statement->execute([':tenant' => $this->tenant->toString(), ':status' => 'ready']);
        $checked = 0;
        $paid = 0;
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $checked++;
            if ($this->invoiceIsUnpaid !== null
                && !($this->invoiceIsUnpaid)((string)$row['invoice_public_id'])) {
                continue;
            }
            foreach ($this->gateway->paymentsForLink((string)$row['provider_link_id']) as $paymentId) {
                if ($this->synchronize((string)$row['invoice_public_id'], $paymentId)) {
                    $paid++;
                    break;
                }
            }
        }
        return ['checked' => $checked, 'paid' => $paid];
    }

    /** Verifies authoritative Mollie status and membership of this exact link. */
    public function synchronize(string $invoicePublicId, string $paymentId): bool
    {
        if (!preg_match('/^tr_[A-Za-z0-9]+$/D', $paymentId)) {
            throw new \InvalidArgumentException('Ongeldig Mollie-betalingsnummer.');
        }
        $link = $this->find($invoicePublicId);
        if ($link === null || !in_array($link['status'], ['ready', 'paid'], true)) {
            throw new \DomainException('Geen actieve factuurbetaallink gevonden.');
        }
        $payment = $this->gateway->payment($paymentId, (string)$link['provider_link_id']);
        if ($payment['id'] !== $paymentId || !$payment['belongs_to_link']
            || $payment['currency'] !== $link['currency']) {
            throw new \DomainException('Mollie-betaling hoort niet bij deze factuurbetaallink.');
        }
        $minor = Money::fromDecimal((string)$payment['amount'], new \Flexgrid\Modules\AdminCore\ValueObject\Currency((string)$link['currency']))->getMinorUnits();
        if ($minor !== (int)$link['amount_minor']) {
            throw new \DomainException('Mollie-betaling heeft een ander bedrag dan de factuurbetaallink.');
        }
        if ($payment['status'] !== 'paid') {
            return false;
        }
        if ($link['status'] === 'paid' && $link['payment_id'] === $paymentId) {
            return true;
        }
        $bookedOn = substr((string)$payment['paid_at'], 0, 10);
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $bookedOn) !== 1) {
            throw new \DomainException('Mollie gaf geen geldige betaaldatum terug.');
        }
        ($this->recordPayment)(
            $invoicePublicId, (string)$payment['amount'], (string)$payment['currency'],
            $paymentId, $bookedOn
        );
        $this->update($invoicePublicId, 'paid', (string)$link['provider_link_id'], (string)$link['url'], $paymentId, '');
        return true;
    }

    private function update(
        string $invoicePublicId, string $status, string $providerId,
        string $url, string $paymentId, string $error
    ): void {
        $statement = $this->connection->prepare(
            'UPDATE `admin_invoice_payment_link` SET `status`=:status,`provider_link_id`=:provider,'
            . '`url`=:url,`payment_id`=:payment,`error`=:error,`updated_at`=:updated '
            . 'WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice'
        );
        $statement->execute([
            ':status' => $status, ':provider' => $providerId ?: null, ':url' => $url ?: null,
            ':payment' => $paymentId ?: null, ':error' => $error ?: null, ':updated' => time(),
            ':tenant' => $this->tenant->toString(), ':invoice' => $invoicePublicId,
        ]);
    }
}
