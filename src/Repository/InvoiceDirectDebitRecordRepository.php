<?php

namespace Flexgrid\Modules\AdminPayment\Repository;

use Repository\Repository;
use Flexgrid\Modules\AdminPayment\Entity\InvoiceDirectDebitRecord;

final class InvoiceDirectDebitRecordRepository extends Repository
{
    public function getEntity() { return new InvoiceDirectDebitRecord(); }
}
