<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Infrastructure\Persistence;

use Flexgrid\Modules\AdminCore\ValueObject\Currency;
use Flexgrid\Modules\AdminCore\ValueObject\Money;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;
use Flexgrid\Modules\AdminPayment\Contract\PaymentRepositoryInterface;
use Flexgrid\Modules\AdminPayment\Application\ReadModel\PaymentAllocationView;
use Flexgrid\Modules\AdminPayment\Domain\Model\Payment;
use Flexgrid\Modules\AdminPayment\Domain\Model\PaymentAllocation;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\BookingDate;
use Flexgrid\Modules\AdminPayment\Domain\ValueObject\PaymentType;
use Flexgrid\Modules\AdminPayment\Exception\DuplicatePaymentReferenceException;

final class PdoPaymentRepository implements PaymentRepositoryInterface
{
    private const PAYMENT_TABLE = 'admin_payment';
    private const ALLOCATION_TABLE = 'admin_payment_allocation';

    /** @var \PDO */ private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public function insert(Payment $payment, int $createdAt): void
    {
        $this->assertTransaction();
        if ($createdAt < 0 || !$payment->isFullyAllocated()) {
            throw new \DomainException('Deze eerste payment-slice vereist een volledig afgeletterde betaling.');
        }
        $statement = $this->connection->prepare(
            'INSERT INTO `' . self::PAYMENT_TABLE . '` ('
            . '`tenant_id`, `public_id`, `payment_type`, `amount_minor`, `currency`, `booked_on`, '
            . '`description`, `reference`, `source`, `external_id`, `created_at`, `updated_at`'
            . ') VALUES ('
            . ':tenant_id, :public_id, :payment_type, :amount_minor, :currency, :booked_on, '
            . ':description, :reference, :source, :external_id, :created_at, :updated_at'
            . ')'
        );
        try {
            $statement->execute([
                ':tenant_id' => $payment->getTenantId()->toString(),
                ':public_id' => $payment->getPublicId(),
                ':payment_type' => $payment->getType()->getValue(),
                ':amount_minor' => $payment->getAmount()->getMinorUnits(),
                ':currency' => $payment->getCurrency()->getCode(),
                ':booked_on' => $payment->getBookedOn()->getValue(),
                ':description' => $this->nullable($payment->getDescription()),
                ':reference' => $this->nullable($payment->getReference()),
                ':source' => $payment->getSource(),
                ':external_id' => $this->nullable($payment->getExternalId()),
                ':created_at' => $createdAt,
                ':updated_at' => $createdAt,
            ]);
        } catch (\PDOException $exception) {
            if ((string)$exception->getCode() === '23000' && $payment->getExternalId() !== '') {
                throw new DuplicatePaymentReferenceException(
                    'Externe betaalreferentie bestaat al.',
                    0,
                    $exception
                );
            }
            throw $exception;
        }

        $paymentId = (int)$this->connection->lastInsertId();
        if ($paymentId < 1) {
            throw new \RuntimeException('Database gaf geen geldige interne payment-id terug.');
        }
        $allocationStatement = $this->connection->prepare(
            'INSERT INTO `' . self::ALLOCATION_TABLE . '` ('
            . '`tenant_id`, `public_id`, `payment_id`, `target_type`, `target_public_id`, '
            . '`amount_minor`, `allocated_at`, `created_at`'
            . ') VALUES ('
            . ':tenant_id, :public_id, :payment_id, :target_type, :target_public_id, '
            . ':amount_minor, :allocated_at, :created_at'
            . ')'
        );
        foreach ($payment->getAllocations() as $allocation) {
            $allocationStatement->execute([
                ':tenant_id' => $payment->getTenantId()->toString(),
                ':public_id' => $allocation->getPublicId(),
                ':payment_id' => $paymentId,
                ':target_type' => $allocation->getTargetType(),
                ':target_public_id' => $allocation->getTargetPublicId(),
                ':amount_minor' => $allocation->getAmount()->getMinorUnits(),
                ':allocated_at' => $allocation->getAllocatedAt(),
                ':created_at' => $createdAt,
            ]);
        }
    }

    public function findByExternalReference(TenantId $tenantId, string $source, string $externalId): ?Payment
    {
        $source = strtolower(trim($source));
        $externalId = trim($externalId);
        if ($source === '' || $externalId === '') {
            return null;
        }
        $statement = $this->connection->prepare(
            'SELECT * FROM `' . self::PAYMENT_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `source` = :source AND `external_id` = :external_id LIMIT 1'
        );
        $statement->execute([
            ':tenant_id' => $tenantId->toString(),
            ':source' => $source,
            ':external_id' => $externalId,
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($tenantId, $row) : null;
    }

    public function getAllocatedTotal(
        TenantId $tenantId,
        string $targetType,
        string $targetPublicId,
        Currency $currency
    ): Money {
        $statement = $this->connection->prepare(
            'SELECT COALESCE(SUM(`allocation`.`amount_minor`), 0) FROM `' . self::ALLOCATION_TABLE . '` AS `allocation` '
            . 'INNER JOIN `' . self::PAYMENT_TABLE . '` AS `payment` '
            . 'ON `payment`.`id` = `allocation`.`payment_id` AND `payment`.`tenant_id` = `allocation`.`tenant_id` '
            . 'WHERE `allocation`.`tenant_id` = :tenant_id AND `allocation`.`target_type` = :target_type '
            . 'AND `allocation`.`target_public_id` = :target_public_id AND `payment`.`currency` = :currency'
        );
        $statement->execute([
            ':tenant_id' => $tenantId->toString(),
            ':target_type' => $targetType,
            ':target_public_id' => $targetPublicId,
            ':currency' => $currency->getCode(),
        ]);
        return new Money((int)$statement->fetchColumn(), $currency);
    }

    public function listAllocationsForTarget(
        TenantId $tenantId,
        string $targetType,
        string $targetPublicId,
        Currency $currency
    ): array {
        $statement = $this->connection->prepare(
            'SELECT `payment`.`public_id` AS `payment_public_id`, '
            . '`allocation`.`public_id` AS `allocation_public_id`, `payment`.`payment_type`, '
            . '`allocation`.`amount_minor`, `payment`.`booked_on`, `payment`.`description`, '
            . '`payment`.`reference`, `payment`.`source` '
            . 'FROM `' . self::ALLOCATION_TABLE . '` AS `allocation` '
            . 'INNER JOIN `' . self::PAYMENT_TABLE . '` AS `payment` '
            . 'ON `payment`.`id` = `allocation`.`payment_id` AND `payment`.`tenant_id` = `allocation`.`tenant_id` '
            . 'WHERE `allocation`.`tenant_id` = :tenant_id AND `allocation`.`target_type` = :target_type '
            . 'AND `allocation`.`target_public_id` = :target_public_id AND `payment`.`currency` = :currency '
            . 'ORDER BY `payment`.`booked_on` DESC, `payment`.`id` DESC, `allocation`.`id` DESC'
        );
        $statement->execute([
            ':tenant_id' => $tenantId->toString(),
            ':target_type' => strtolower(trim($targetType)),
            ':target_public_id' => trim($targetPublicId),
            ':currency' => $currency->getCode(),
        ]);

        $items = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $items[] = new PaymentAllocationView(
                (string)$row['payment_public_id'],
                (string)$row['allocation_public_id'],
                new PaymentType((string)$row['payment_type']),
                new Money((int)$row['amount_minor'], $currency),
                new BookingDate((string)$row['booked_on']),
                (string)($row['description'] ?? ''),
                (string)($row['reference'] ?? ''),
                (string)$row['source']
            );
        }

        return $items;
    }

    private function hydrate(TenantId $tenantId, array $row): Payment
    {
        $currency = new Currency((string)$row['currency']);
        $payment = new Payment(
            (string)$row['public_id'],
            $tenantId,
            new PaymentType((string)$row['payment_type']),
            new Money((int)$row['amount_minor'], $currency),
            new BookingDate((string)$row['booked_on']),
            (string)($row['description'] ?? ''),
            (string)($row['reference'] ?? ''),
            (string)$row['source'],
            (string)($row['external_id'] ?? '')
        );
        $statement = $this->connection->prepare(
            'SELECT `public_id`, `target_type`, `target_public_id`, `amount_minor`, `allocated_at` '
            . 'FROM `' . self::ALLOCATION_TABLE . '` '
            . 'WHERE `tenant_id` = :tenant_id AND `payment_id` = :payment_id ORDER BY `id`'
        );
        $statement->execute([
            ':tenant_id' => $tenantId->toString(),
            ':payment_id' => (int)$row['id'],
        ]);
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $allocation) {
            $payment->allocate(new PaymentAllocation(
                (string)$allocation['public_id'],
                (string)$allocation['target_type'],
                (string)$allocation['target_public_id'],
                new Money((int)$allocation['amount_minor'], $currency),
                (int)$allocation['allocated_at']
            ));
        }
        return $payment;
    }

    private function nullable(string $value)
    {
        return $value === '' ? null : $value;
    }

    private function assertTransaction(): void
    {
        if (!$this->connection->inTransaction()) {
            throw new \LogicException('Payment-opslag vereist een actieve transactie.');
        }
    }
}
