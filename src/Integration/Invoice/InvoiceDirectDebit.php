<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminInvoice\Domain\Model\Invoice;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\InvoiceStatus;
use Flexgrid\Modules\AdminInvoice\Domain\ValueObject\CalendarDate;

/** Tenant- en providergebonden workflow; claims en saldo-controles vereisen deze transactielaag. */
final class InvoiceDirectDebit
{
    private const CUSTOMERS = 'admin_invoice_debit_customer';
    private const DEBITS = 'admin_invoice_direct_debit';
    private $connection;
    private $tenant;
    private $gateway;
    private $recordPayment;
    private $invoice;
    private $enabled;
    private $days;
    private $webhookBase;
    private $outstanding;

    public function __construct(\PDO $connection, TenantId $tenant, DirectDebitGatewayInterface $gateway,
        callable $recordPayment, callable $invoice, callable $enabled, callable $days, string $webhookBase, callable $outstanding)
    {
        $this->connection = $connection;
        $this->tenant = $tenant;
        $this->gateway = $gateway;
        $this->recordPayment = $recordPayment;
        $this->invoice = $invoice;
        $this->enabled = $enabled;
        $this->days = $days;
        $this->outstanding = $outstanding;
        if (!preg_match('~^https://[^\s]+$~iD', $webhookBase)) {
            throw new \LogicException('Factuurincasso vereist een publiek bereikbare HTTPS-webhook.');
        }
        $this->webhookBase = rtrim($webhookBase, '/');
    }

    public function customer(string $id): ?array { return $this->find(self::CUSTOMERS, 'customer_public_id', $id); }
    public function findDebit(string $id): ?array { return $this->find(self::DEBITS, 'invoice_public_id', $id); }

    public function createSetup(string $customerId, string $name, string $email, string $redirectUrl): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim($name) === '' || strlen($customerId) > 64 || $customerId === '') {
            throw new \InvalidArgumentException('Een actieve factuurklant met naam en e-mailadres is verplicht.');
        }
        if (!preg_match('~^https://[^\s]+$~iD', $redirectUrl)) { throw new \LogicException('Stel eerst een publieke HTTPS-bedankpagina in voor Mollie.'); }
        $existing = $this->customer($customerId);
        $attempt = 1;
        if ($existing !== null) {
            $this->assertProfile($existing);
            if ($existing['status'] === 'link_ready') { return $existing; }
            if (!in_array($existing['status'], ['failed', 'canceled', 'expired', 'invalid'], true)) {
                throw new \DomainException('Er bestaat al een machtigingsaanvraag. Controleer eerst de Mollie-status.');
            }
            $attempt = (int)$existing['setup_attempt'] + 1;
            $claim = $this->connection->prepare('UPDATE `' . self::CUSTOMERS . '` SET `status`=:creating,`setup_attempt`=:attempt,'
                . '`setup_payment_id`=NULL,`setup_url`=NULL,`mandate_id`=NULL,`consent_at`=NULL,`enabled`=0,`error`=NULL,`updated_at`=:now '
                . 'WHERE `tenant_id`=:tenant AND `customer_public_id`=:id AND `status`=:previous AND `setup_attempt`=:old_attempt');
            $claim->execute([':creating' => 'creating', ':attempt' => $attempt, ':now' => time(), ':tenant' => $this->tenant->toString(),
                ':id' => $customerId, ':previous' => $existing['status'], ':old_attempt' => $existing['setup_attempt']]);
            if ($claim->rowCount() !== 1) { throw new \DomainException('De machtigingsaanvraag wordt al vernieuwd.'); }
        } else {
            $profile = $this->gateway->profile();
            $this->insert(self::CUSTOMERS, ['customer_public_id' => $customerId, 'profile_id' => $profile['id'],
                'mode' => $profile['mode'], 'status' => 'creating', 'enabled' => 0, 'setup_attempt' => 1]);
        }
        try {
            $remoteId = $existing['provider_customer_id'] ?? '';
            if ($remoteId === '') { $remoteId = $this->gateway->createCustomer($name, $email, $this->key('customer', $customerId)); }
            if (!preg_match('/^cst_[A-Za-z0-9]+$/D', $remoteId)) { throw new \UnexpectedValueException('Geen geldige Mollie-klant ontvangen.'); }
            $this->update(self::CUSTOMERS, 'customer_public_id', $customerId, ['provider_customer_id' => $remoteId]);
            $first = $this->gateway->createFirst($remoteId, $this->webhookBase . '/setup/' . rawurlencode($customerId),
                $redirectUrl, $this->key('first', $customerId . ':' . $attempt));
            if (!preg_match('/^tr_[A-Za-z0-9]+$/D', (string)$first['id'])
                || !preg_match('~^https://[^\s]+$~iD', (string)$first['url'])) {
                throw new \UnexpectedValueException('Geen geldige Mollie-toestemmingslink ontvangen.');
            }
            $this->update(self::CUSTOMERS, 'customer_public_id', $customerId,
                ['setup_payment_id' => $first['id'], 'setup_url' => $first['url'], 'status' => 'link_ready']);
        } catch (\Throwable $exception) {
            $this->update(self::CUSTOMERS, 'customer_public_id', $customerId, ['status' => 'attention', 'error' => 'Aanvraag onduidelijk. Controleer Mollie voordat u opnieuw begint.']);
            throw $exception;
        }
        return $this->customer($customerId);
    }

    public function syncSetup(string $customerId, string $paymentId = ''): array
    {
        $row = $this->customer($customerId);
        if ($row === null || empty($row['setup_payment_id'])) { throw new \DomainException('Geen machtigingsbetaling gevonden.'); }
        $this->assertProfile($row);
        if ($paymentId !== '' && $paymentId !== $row['setup_payment_id']) { throw new \DomainException('Deze betaling hoort niet bij de machtigingsaanvraag.'); }
        $payment = $this->gateway->payment((string)$row['setup_payment_id']);
        if ($payment['id'] !== $row['setup_payment_id'] || $payment['customer_id'] !== $row['provider_customer_id']
            || $payment['sequence_type'] !== 'first' || $payment['profile_id'] !== $row['profile_id']
            || $payment['mode'] !== $row['mode'] || $payment['currency'] !== 'EUR'
            || Money::fromDecimal($payment['amount'], Currency::euro())->getMinorUnits() !== 1) {
            throw new \DomainException('Mollie gaf een afwijkende machtigingsbetaling terug.');
        }
        if ($payment['status'] === 'paid') {
            $mandate = $this->gateway->mandate($payment['customer_id'], $payment['mandate_id']);
            $valid = $mandate['id'] === $payment['mandate_id'] && $mandate['method'] === 'directdebit' && $mandate['status'] === 'valid';
            $this->update(self::CUSTOMERS, 'customer_public_id', $customerId, [
                'mandate_id' => $payment['mandate_id'], 'status' => $valid ? 'valid' : 'invalid',
                'enabled' => $valid && empty($row['consent_at']) ? 1 : ($valid ? (int)$row['enabled'] : 0),
                'consent_at' => $row['consent_at'] ?? time(), 'error' => null,
            ]);
        } elseif (in_array($payment['status'], ['failed', 'canceled', 'expired'], true)) {
            $this->update(self::CUSTOMERS, 'customer_public_id', $customerId, ['status' => $payment['status'], 'enabled' => 0]);
        }
        return $this->customer($customerId);
    }

    public function setCustomerEnabled(string $customerId, bool $enabled): void
    {
        $row = $this->customer($customerId);
        if ($row === null) { throw new \DomainException('Vraag eerst toestemming voor automatische incasso aan.'); }
        if ($enabled) { $this->assertMandate($row); }
        $this->update(self::CUSTOMERS, 'customer_public_id', $customerId, ['enabled' => $enabled ? 1 : 0]);
    }

    public function prepare(Invoice $invoice, ?string $today = null): string
    {
        $today = $today ?? date('Y-m-d');
        new CalendarDate($today);
        $this->assertInvoice($invoice);
        $existing = $this->findDebit($invoice->getPublicId());
        if ($existing !== null && $existing['status'] !== 'awaiting_notice') {
            if (in_array($existing['status'], ['scheduled', 'pending', 'paid'], true)) { return ''; }
            throw new \DomainException('Een eerdere incasso vereist controle. Deze factuur wordt niet opnieuw geïncasseerd.');
        }
        if (!($this->enabled)()) {
            if ($existing !== null) { throw new \DomainException('Incasso is gepauzeerd; controleer de bestaande planning.'); }
            return '';
        }
        $customerId = $invoice->getCustomerSnapshot()->getSourcePublicId();
        $customer = $customerId !== '' ? $this->customer($customerId) : null;
        if ($customer === null || !(bool)$customer['enabled']) {
            if ($existing !== null) { throw new \DomainException('Incasso is voor deze klant gepauzeerd.'); }
            return '';
        }
        $this->assertMandate($customer);
        if ($customer['mode'] === 'live' && defined('__MAIL_CATCH_ALL__')
            && trim((string)__MAIL_CATCH_ALL__) !== ''
            && strcasecmp(trim((string)__MAIL_CATCH_ALL__), (string)$invoice->getCustomerSnapshot()->toArray()['email']) !== 0) {
            throw new \DomainException('De factuurmail wordt naar een catchall omgeleid. Een live-incasso kan pas na aankondiging aan de klant worden gepland.');
        }
        $amount = $invoice->getFinalizedTotals()->getGross();
        if ($amount->getCurrency()->getCode() !== 'EUR' || $amount->getMinorUnits() <= 0) {
            throw new \DomainException('SEPA-incasso vereist een positief factuurbedrag in EUR.');
        }
        $date = (new \DateTimeImmutable($today))->modify('+' . max(1, (int)($this->days)()) . ' days')->format('Y-m-d');
        $date = max($date, $invoice->getDueDate()->getValue());
        $notice = 'Het bedrag van EUR ' . $amount->format(',', '.') . ' wordt op of na '
            . (new \DateTimeImmutable($date))->format('d-m-Y')
            . ' automatisch van uw rekening geïncasseerd via uw SEPA-machtiging. U hoeft deze factuur niet zelf over te maken.';
        if ($customer['mode'] === 'test') { $notice = 'TEST — er wordt geen echte incasso uitgevoerd. ' . $notice; }
        $ownsTransaction = !$this->connection->inTransaction();
        try {
        if ($ownsTransaction) { $this->connection->beginTransaction(); }
        $this->assertOutstanding($invoice->getPublicId(), $amount->getMinorUnits());
        if ($existing === null) {
            $this->insert(self::DEBITS, ['invoice_public_id' => $invoice->getPublicId(), 'customer_public_id' => $customerId,
                'invoice_number' => $invoice->getInvoiceNumber(), 'customer_name' => $invoice->getCustomerSnapshot()->getName(),
                'provider_customer_id' => $customer['provider_customer_id'], 'mandate_id' => $customer['mandate_id'],
                'profile_id' => $customer['profile_id'], 'mode' => $customer['mode'], 'status' => 'awaiting_notice',
                'amount_minor' => $amount->getMinorUnits(), 'currency' => 'EUR', 'collect_on' => $date, 'notice' => $notice]);
        } else {
            $this->update(self::DEBITS, 'invoice_public_id', $invoice->getPublicId(), ['collect_on' => $date, 'notice' => $notice]);
        }
        if ($ownsTransaction) { $this->connection->commit(); }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) { $this->connection->rollBack(); }
            throw $exception;
        }
        return $notice;
    }

    public function noticeSent(string $invoiceId): void
    {
        $row = $this->findDebit($invoiceId);
        if ($row === null || $row['status'] !== 'awaiting_notice') { return; }
        $this->update(self::DEBITS, 'invoice_public_id', $invoiceId,
            ['status' => 'scheduled', 'notice_sent_at' => time()]);
    }

    public function runDue(string $today, int $limit = 100): array
    {
        new CalendarDate($today);
        $result = ['started' => 0, 'stopped' => 0, 'failed' => 0];
        if (!($this->enabled)()) { return $result; }
        $statement = $this->connection->prepare('SELECT * FROM `' . self::DEBITS . '` WHERE `tenant_id`=:tenant '
            . 'AND `status`=:status AND `collect_on`<=:today ORDER BY `collect_on` LIMIT ' . max(1, min(500, $limit)));
        $statement->execute([':tenant' => $this->tenant->toString(), ':status' => 'scheduled', ':today' => $today]);
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $id = (string)$row['invoice_public_id'];
            $claimed = false;
            try {
                $customer = $this->customer((string)$row['customer_public_id']);
                if ($customer === null || !(bool)$customer['enabled']) {
                    $this->update(self::DEBITS, 'invoice_public_id', $id, ['status' => 'stopped', 'error' => 'Incasso is voor deze klant gepauzeerd.'], 'scheduled');
                    $result['stopped']++;
                    continue;
                }
                $this->assertMandate($customer);
                $this->assertProfile($row);
                if ($row['mandate_id'] !== $customer['mandate_id'] || $row['provider_customer_id'] !== $customer['provider_customer_id']
                    || empty($row['notice_sent_at'])) { throw new \DomainException('Machtiging of aankondiging is gewijzigd; controleer de planning.'); }
                $invoice = ($this->invoice)($id);
                $this->assertInvoice($invoice);
                $this->connection->beginTransaction();
                try {
                    // De publieke Invoice-poort vergrendelt de factuur, zoals bij betalingsboekingen.
                    $this->assertOutstanding($id, (int)$row['amount_minor']);
                    $claim = $this->connection->prepare('UPDATE `' . self::DEBITS . '` SET `status`=:creating,`updated_at`=:now '
                        . 'WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice AND `status`=:scheduled');
                    $claim->execute([':creating' => 'creating', ':now' => time(), ':tenant' => $this->tenant->toString(),
                        ':invoice' => $id, ':scheduled' => 'scheduled']);
                    $claimed = $claim->rowCount() === 1;
                    $this->connection->commit();
                } catch (\Throwable $exception) { $this->connection->rollBack(); throw $exception; }
                if (!$claimed) { continue; }
                $payment = $this->gateway->collect([
                    'customerId' => $row['provider_customer_id'], 'mandateId' => $row['mandate_id'],
                    'amount' => ['currency' => $row['currency'], 'value' => (new Money((int)$row['amount_minor'], Currency::euro()))->format('.', '')],
                    'description' => 'Factuur ' . $invoice->getInvoiceNumber(),
                    'webhookUrl' => $this->webhookBase . '/payment/' . rawurlencode($id),
                    'metadata' => ['invoice_public_id' => $id, 'tenant_id' => $this->tenant->toString()],
                ], $this->key('debit', $id));
                if (!preg_match('/^tr_[A-Za-z0-9]+$/D', (string)$payment['id'])) { throw new \UnexpectedValueException('Mollie gaf geen geldige incassobetaling terug.'); }
                $this->update(self::DEBITS, 'invoice_public_id', $id, ['status' => 'pending', 'payment_id' => $payment['id'], 'error' => null]);
                $result['started']++;
            } catch (\Throwable $exception) {
                $failure = $this->connection->prepare('UPDATE `' . self::DEBITS . '` SET `status`=:attention,`error`=:error,`updated_at`=:now '
                    . 'WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice AND `status`=:expected');
                $failure->execute([':attention' => 'attention', ':error' => substr('Incasso vereist controle: ' . $exception->getMessage(), 0, 1000),
                    ':now' => time(), ':tenant' => $this->tenant->toString(), ':invoice' => $id, ':expected' => $claimed ? 'creating' : 'scheduled']);
                $result['failed']++;
            }
        }
        return $result;
    }

    public function synchronize(string $invoiceId, string $paymentId = ''): array
    {
        $row = $this->findDebit($invoiceId);
        if ($row === null || empty($row['payment_id'])) { throw new \DomainException('Er is nog geen incassobetaling om te controleren.'); }
        $this->assertProfile($row);
        if ($paymentId !== '' && $paymentId !== $row['payment_id']) { throw new \DomainException('Betaling hoort niet bij deze incasso.'); }
        $payment = $this->gateway->payment((string)$row['payment_id']);
        $this->verifyDebitPayment($row, $payment);
        $status = in_array($payment['status'], ['failed', 'canceled', 'expired'], true) ? $payment['status'] : 'pending';
        if ($payment['status'] === 'paid') {
            $date = substr($payment['paid_at'], 0, 10);
            new CalendarDate($date);
            ($this->recordPayment)($invoiceId, $payment['amount'], $payment['currency'], $row['payment_id'], $date, 'receipt');
            $status = 'paid';
            foreach ($this->gateway->chargebacks((string)$row['payment_id']) as $chargeback) {
                if (!preg_match('/^chb_[A-Za-z0-9]+$/D', $chargeback['id']) || $chargeback['currency'] !== $row['currency']) {
                    throw new \DomainException('Ongeldige terugboeking ontvangen.');
                }
                $amount = Money::fromDecimal($chargeback['amount'], Currency::euro());
                if ($amount->getMinorUnits() <= 0 || $amount->getMinorUnits() > (int)$row['amount_minor']) {
                    throw new \DomainException('Ongeldig bedrag bij terugboeking.');
                }
                $date = substr($chargeback['created_at'], 0, 10);
                new CalendarDate($date);
                ($this->recordPayment)($invoiceId, '-' . $amount->format('.', ''), 'EUR', $chargeback['id'], $date, 'refund');
                if ($chargeback['reversed_at'] !== '') {
                    $reversed = substr($chargeback['reversed_at'], 0, 10);
                    new CalendarDate($reversed);
                    ($this->recordPayment)($invoiceId, $amount->format('.', ''), 'EUR', $chargeback['id'] . ':reversed', $reversed, 'receipt');
                } else { $status = 'chargeback'; }
            }
        }
        $this->update(self::DEBITS, 'invoice_public_id', $invoiceId, ['status' => $status, 'error' => null]);
        return $this->findDebit($invoiceId);
    }

    public function recoverPayment(string $invoiceId, string $paymentId): array
    {
        $row = $this->findDebit($invoiceId);
        if ($row === null || !empty($row['payment_id']) || !in_array($row['status'], ['attention', 'creating'], true)
            || !preg_match('/^tr_[A-Za-z0-9]+$/D', $paymentId)
            || ($row['status'] === 'creating' && (int)$row['updated_at'] > time() - 900)) {
            throw new \DomainException('Deze incasso kan nu niet met een Mollie-betaling worden hersteld.');
        }
        $this->assertProfile($row);
        $candidate = $row; $candidate['payment_id'] = $paymentId;
        $this->verifyDebitPayment($candidate, $this->gateway->payment($paymentId));
        $statement = $this->connection->prepare('UPDATE `' . self::DEBITS . '` SET `payment_id`=:payment,`status`=:pending,`updated_at`=:now '
            . 'WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice AND `payment_id` IS NULL AND `status`=:previous');
        $statement->execute([':payment' => $paymentId, ':pending' => 'pending', ':now' => time(), ':tenant' => $this->tenant->toString(),
            ':invoice' => $invoiceId, ':previous' => $row['status']]);
        if ($statement->rowCount() !== 1) { throw new \DomainException('Deze incasso is al door een andere actie gewijzigd.'); }
        return $this->synchronize($invoiceId, $paymentId);
    }

    private function verifyDebitPayment(array $row, array $payment): void
    {
        if ($payment['id'] !== $row['payment_id'] || $payment['customer_id'] !== $row['provider_customer_id']
            || $payment['mandate_id'] !== $row['mandate_id'] || $payment['profile_id'] !== $row['profile_id']
            || $payment['mode'] !== $row['mode'] || $payment['method'] !== 'directdebit' || $payment['sequence_type'] !== 'recurring'
            || $payment['currency'] !== $row['currency'] || ($payment['metadata']['invoice_public_id'] ?? '') !== $row['invoice_public_id']
            || ($payment['metadata']['tenant_id'] ?? '') !== $this->tenant->toString()
            || Money::fromDecimal($payment['amount'], Currency::euro())->getMinorUnits() !== (int)$row['amount_minor']) {
            throw new \DomainException('Mollie-betaling wijkt af van de opgeslagen factuurincasso.');
        }
    }

    public function synchronizeOpen(): array
    {
        $statement = $this->connection->prepare('SELECT * FROM `' . self::DEBITS . '` WHERE `tenant_id`=:tenant '
            . 'AND `status` IN (:pending,:paid,:chargeback) ORDER BY `updated_at`,`created_at` LIMIT 100');
        $statement->execute([':tenant' => $this->tenant->toString(), ':pending' => 'pending', ':paid' => 'paid', ':chargeback' => 'chargeback']);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $result = ['checked' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            if (empty($row['payment_id']) || !in_array($row['status'], ['pending', 'paid', 'chargeback'], true)) { continue; }
            try { $this->synchronize($row['invoice_public_id']); $result['checked']++; }
            catch (\Throwable $exception) {
                $this->update(self::DEBITS, 'invoice_public_id', $row['invoice_public_id'], ['error' => 'Statuscontrole vereist aandacht.']);
                $result['failed']++;
            }
        }
        return $result;
    }

    public function overview(): array
    {
        $statement = $this->connection->prepare('SELECT * FROM `' . self::DEBITS . '` WHERE `tenant_id`=:tenant ORDER BY `created_at` DESC LIMIT 500');
        $statement->execute([':tenant' => $this->tenant->toString()]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function assertNoActiveDebit(string $invoiceId): void
    {
        $row = $this->findDebit($invoiceId);
        if ($row !== null && !in_array($row['status'], ['failed', 'canceled', 'expired', 'stopped', 'chargeback'], true)) {
            throw new \DomainException('Voor deze factuur staat een incasso gepland of in verwerking. Controleer die eerst.');
        }
    }

    private function assertInvoice(Invoice $invoice): void
    {
        if (!$invoice->getTenantId()->equals($this->tenant) || $invoice->getStatus()->getValue() !== InvoiceStatus::FINALIZED) {
            throw new \DomainException('Alleen een definitieve factuur van deze administratie kan worden geïncasseerd.');
        }
    }

    private function assertMandate(array $customer): void
    {
        $this->assertProfile($customer);
        if (empty($customer['consent_at']) || empty($customer['mandate_id'])) { throw new \DomainException('De klant heeft nog geen incassomachtiging afgerond.'); }
        $mandate = $this->gateway->mandate((string)$customer['provider_customer_id'], (string)$customer['mandate_id']);
        if ($mandate['id'] !== $customer['mandate_id'] || $mandate['method'] !== 'directdebit' || $mandate['status'] !== 'valid') {
            $this->update(self::CUSTOMERS, 'customer_public_id', $customer['customer_public_id'], ['enabled' => 0, 'status' => 'invalid']);
            throw new \DomainException('De SEPA-machtiging is niet meer geldig. Vraag opnieuw toestemming aan.');
        }
    }

    private function assertProfile(array $row): void
    {
        $profile = $this->gateway->profile();
        if ($row['profile_id'] !== $profile['id'] || $row['mode'] !== $profile['mode']) {
            throw new \DomainException('Mollie-profiel of test/live-modus wijkt af van de opgeslagen machtiging.');
        }
    }

    private function assertOutstanding(string $invoiceId, int $amount): void
    {
        ($this->outstanding)($invoiceId, $amount);
        $link = $this->connection->prepare('SELECT 1 FROM `admin_invoice_payment_link` WHERE `tenant_id`=:tenant AND `invoice_public_id`=:invoice');
        $link->execute([':tenant' => $this->tenant->toString(), ':invoice' => $invoiceId]);
        if ($link->fetchColumn()) { throw new \DomainException('Er bestaat al een Mollie-betaallink voor deze factuur. Gebruik één betaalroute.'); }
    }

    private function find(string $table, string $column, string $id): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM `' . $table . '` WHERE `tenant_id`=:tenant AND `' . $column . '`=:id');
        $statement->execute([':tenant' => $this->tenant->toString(), ':id' => $id]);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $row = $rows[0] ?? null;
        return is_array($row) ? $row : null;
    }

    private function insert(string $table, array $values): void
    {
        $values += ['tenant_id' => $this->tenant->toString(), 'created_at' => time(), 'updated_at' => time()];
        $keys = array_keys($values);
        $statement = $this->connection->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', $keys) . '`) VALUES (:' . implode(',:', $keys) . ')');
        $statement->execute($values);
    }

    private function update(string $table, string $column, string $id, array $values, ?string $expectedStatus = null): void
    {
        $values['updated_at'] = time();
        $sets = [];
        foreach ($values as $key => $value) { $sets[] = '`' . $key . '`=:' . $key; }
        $values['tenant'] = $this->tenant->toString();
        $values['id'] = $id;
        $statement = $this->connection->prepare('UPDATE `' . $table . '` SET ' . implode(',', $sets) . ' WHERE `tenant_id`=:tenant AND `' . $column . '`=:id'
            . ($expectedStatus !== null ? ' AND `status`=:expected_status' : ''));
        if ($expectedStatus !== null) { $values['expected_status'] = $expectedStatus; }
        $statement->execute($values);
    }

    private function key(string $kind, string $id): string { return hash('sha256', $this->tenant->toString() . ':' . $kind . ':' . $id); }
}
