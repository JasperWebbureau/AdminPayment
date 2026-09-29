<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Repository;

use Flexgrid\Modules\AdminPayment\Entity\PaymentRecord;
use Repository\Repository;

final class PaymentRecordRepository extends Repository
{
    public function getEntity()
    {
        return new PaymentRecord();
    }
}
