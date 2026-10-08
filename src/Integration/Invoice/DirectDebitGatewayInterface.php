<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

interface DirectDebitGatewayInterface
{
    public function profile(): array;
    public function createCustomer(string $name, string $email, string $key): string;
    public function createFirst(string $customerId, string $webhookUrl, string $redirectUrl, string $key): array;
    public function mandate(string $customerId, string $mandateId): array;
    public function payment(string $paymentId): array;
    public function collect(array $data, string $key): array;
    public function chargebacks(string $paymentId): array;
}
