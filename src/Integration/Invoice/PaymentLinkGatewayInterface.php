<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

interface PaymentLinkGatewayInterface
{
    /** @return array{id:string,url:string,mode:string} */
    public function create(string $description, string $currency, string $amount, string $webhookUrl): array;

    /** @return array{id:string,status:string,belongs_to_link:bool,currency:string,amount:string,paid_at:string} */
    public function payment(string $paymentId, string $linkId): array;
}
