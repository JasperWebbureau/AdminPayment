<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Repository;

use Flexgrid\Modules\AdminPayment\Entity\PaymentAllocationRecord;
use Repository\Repository;

final class PaymentAllocationRecordRepository extends Repository
{
    public function getEntity()
    {
        return new PaymentAllocationRecord();
    }
}
