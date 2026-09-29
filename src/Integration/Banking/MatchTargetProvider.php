<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Banking;

use Flexgrid\Database\Connection;
use Flexgrid\Modules\AdminBanking\Application\ReadModel\BankMatchContext;
use Flexgrid\Modules\AdminBanking\Application\ReadModel\BankMatchTarget;
use Flexgrid\Modules\AdminBanking\Contract\BankMatchTargetProviderInterface;
use Flexgrid\Modules\AdminCore\ValueObject\TenantId;

final class MatchTargetProvider implements BankMatchTargetProviderInterface
{
    public function supports(string $targetType): bool
    {
        return strtolower(trim($targetType)) === 'invoice';
    }

    public function search(TenantId $tenant, BankMatchContext $context, string $query, int $limit): array
    {
        if ($context->getAmountMinor() <= 0) {
            return [];
        }

        $sql = $this->selectSql() . "
            WHERE i.`tenant_id`=:tenant
              AND i.`status`='final'
              AND i.`currency`=:currency
              AND (
                    i.`invoice_number` LIKE :query
                 OR COALESCE(i.`customer_reference`,'') LIKE :query
                 OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(i.`customer_snapshot`,'$.name')),'') LIKE :query
              )
            GROUP BY i.`id`
            HAVING outstanding_minor>0
            ORDER BY i.`issue_date` DESC, i.`id` DESC
            LIMIT " . max(1, min(50, $limit));
        $statement = Connection::getConnections()->prepare($sql);
        $statement->execute([
            ':tenant' => $tenant->toString(),
            ':currency' => $context->getCurrency(),
            ':query' => '%' . trim($query) . '%',
        ]);

        return array_map(function (array $row) use ($context): BankMatchTarget {
            return $this->map($row, $context);
        }, $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function find(TenantId $tenant, BankMatchContext $context, string $targetPublicId): ?BankMatchTarget
    {
        if ($context->getAmountMinor() <= 0) {
            return null;
        }

        $statement = Connection::getConnections()->prepare($this->selectSql() . "
            WHERE i.`tenant_id`=:tenant
              AND i.`public_id`=:public_id
              AND i.`status`='final'
              AND i.`currency`=:currency
            GROUP BY i.`id`
            HAVING outstanding_minor>0
            LIMIT 1");
        $statement->execute([
            ':tenant' => $tenant->toString(),
            ':public_id' => trim($targetPublicId),
            ':currency' => $context->getCurrency(),
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $this->map($row, $context) : null;
    }

    private function selectSql(): string
    {
        return "SELECT i.`public_id`,i.`invoice_number`,i.`customer_reference`,i.`issue_date`,i.`gross_total_minor`,
                       COALESCE(JSON_UNQUOTE(JSON_EXTRACT(i.`customer_snapshot`,'$.name')),'') AS customer_name,
                       i.`gross_total_minor`-COALESCE(SUM(a.`amount_minor`),0) AS outstanding_minor,
                       i.`currency`
                FROM `admin_invoice` i
                LEFT JOIN `admin_payment_allocation` a
                  ON a.`tenant_id`=i.`tenant_id`
                 AND a.`target_type`='invoice'
                 AND a.`target_public_id`=i.`public_id`";
    }

    private function map(array $row, BankMatchContext $context): BankMatchTarget
    {
        $outstanding = (int)$row['outstanding_minor'];
        $bankAmount = $context->getAmountMinor();
        $selectable = $bankAmount > 0 && $bankAmount <= $outstanding;
        $warning = '';
        if ($bankAmount > $outstanding) {
            $warning = 'Het bankbedrag is hoger dan het actuele openstaande factuurbedrag.';
        } elseif ($bankAmount < $outstanding) {
            $warning = 'Dit bedrag wordt als deelbetaling op de factuur verwerkt.';
        }

        return new BankMatchTarget(
            'invoice',
            (string)$row['public_id'],
            'Factuur ' . ((string)$row['invoice_number'] !== '' ? (string)$row['invoice_number'] : 'zonder nummer'),
            (string)$row['customer_name'],
            (string)($row['customer_reference'] ?: $row['invoice_number']),
            (string)$row['issue_date'],
            (int)$row['gross_total_minor'],
            $outstanding,
            (string)$row['currency'],
            $selectable,
            $warning
        );
    }
}
