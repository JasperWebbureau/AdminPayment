<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Repository;

use Flexgrid\Modules\AdminPayment\Entity\InvoicePaymentLinkRecord;
use Repository\Repository;

final class InvoicePaymentLinkRecordRepository extends Repository
{
    public function getEntity()
    {
        return new InvoicePaymentLinkRecord();
    }
}
