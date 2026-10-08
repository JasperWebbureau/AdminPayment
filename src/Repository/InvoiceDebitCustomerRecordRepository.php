<?php

namespace Flexgrid\Modules\AdminPayment\Repository;

use Repository\Repository;
use Flexgrid\Modules\AdminPayment\Entity\InvoiceDebitCustomerRecord;

final class InvoiceDebitCustomerRecordRepository extends Repository
{
    public function getEntity() { return new InvoiceDebitCustomerRecord(); }
}
