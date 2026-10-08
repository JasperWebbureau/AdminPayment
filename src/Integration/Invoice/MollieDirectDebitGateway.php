<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Mollie\Api\MollieApiClient;

final class MollieDirectDebitGateway implements DirectDebitGatewayInterface
{
    private $client;
    private $mode;
    private $profile;

    public function __construct(string $apiKey)
    {
        if (!preg_match('/^(test|live)_[A-Za-z0-9]+$/D', $apiKey, $match)) {
            throw new \LogicException('Configureer een geldige Mollie API-key voor factuurincasso.');
        }
        $this->mode = $match[1];
        $this->client = new MollieApiClient();
        $this->client->setApiKey($apiKey);
    }

    public function profile(): array
    {
        if ($this->profile === null) {
            $profile = $this->client->profiles->getCurrent();
            $this->profile = ['id' => (string)$profile->id, 'mode' => $this->mode];
        }
        return $this->profile;
    }

    public function createCustomer(string $name, string $email, string $key): string
    {
        $this->client->setIdempotencyKey($key);
        return (string)$this->client->customers->create(['name' => $name, 'email' => $email])->id;
    }

    public function createFirst(string $customerId, string $webhookUrl, string $redirectUrl, string $key): array
    {
        $this->client->setIdempotencyKey($key);
        $payment = $this->client->payments->create([
            'customerId' => $customerId, 'sequenceType' => 'first', 'method' => 'ideal',
            'amount' => ['currency' => 'EUR', 'value' => '0.01'],
            'description' => 'Toestemming automatische incasso van facturen (eenmalig EUR 0,01)',
            'webhookUrl' => $webhookUrl, 'redirectUrl' => $redirectUrl,
        ]);
        return ['id' => (string)$payment->id, 'url' => (string)$payment->getCheckoutUrl()];
    }

    public function mandate(string $customerId, string $mandateId): array
    {
        $mandate = $this->client->customers->get($customerId)->getMandate($mandateId);
        return ['id' => (string)$mandate->id, 'status' => (string)$mandate->status, 'method' => (string)$mandate->method];
    }

    public function payment(string $paymentId): array
    {
        $payment = $this->client->payments->get($paymentId);
        return [
            'id' => (string)$payment->id, 'customer_id' => (string)$payment->customerId,
            'mandate_id' => (string)$payment->mandateId, 'profile_id' => (string)$payment->profileId,
            'mode' => (string)$payment->mode, 'sequence_type' => (string)$payment->sequenceType,
            'method' => (string)$payment->method, 'status' => (string)$payment->status,
            'amount' => (string)$payment->amount->value, 'currency' => (string)$payment->amount->currency,
            'paid_at' => (string)$payment->paidAt, 'metadata' => (array)($payment->metadata ?? []),
        ];
    }

    public function collect(array $data, string $key): array
    {
        $this->client->setIdempotencyKey($key);
        $payment = $this->client->payments->create($data + ['sequenceType' => 'recurring', 'method' => 'directdebit']);
        return ['id' => (string)$payment->id];
    }

    public function chargebacks(string $paymentId): array
    {
        $rows = [];
        foreach ($this->client->payments->get($paymentId)->chargebacks() as $chargeback) {
            $rows[] = ['id' => (string)$chargeback->id, 'amount' => (string)$chargeback->amount->value,
                'currency' => (string)$chargeback->amount->currency, 'created_at' => (string)$chargeback->createdAt,
                'reversed_at' => (string)($chargeback->reversedAt ?? '')];
        }
        return $rows;
    }
}
