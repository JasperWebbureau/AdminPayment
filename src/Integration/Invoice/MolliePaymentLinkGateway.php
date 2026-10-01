<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Mollie\Api\MollieApiClient;

final class MolliePaymentLinkGateway implements PaymentLinkGatewayInterface
{
    private $client;

    public function __construct(string $apiKey)
    {
        if (!preg_match('/^live_[A-Za-z0-9]+$/D', $apiKey)) {
            throw new \LogicException('Configureer een live Mollie-sleutel voor factuurbetalingen.');
        }
        $this->client = new MollieApiClient();
        $this->client->setApiKey($apiKey);
    }

    public function create(string $description, string $currency, string $amount, string $webhookUrl = '', string $redirectUrl = ''): array
    {
        $parameters = [
            'description' => $description,
            'amount' => ['currency' => $currency, 'value' => $amount],
            'reusable' => false,
        ];
        if ($webhookUrl !== '') { $parameters['webhookUrl'] = $webhookUrl; }
        if ($redirectUrl !== '') { $parameters['redirectUrl'] = $redirectUrl; }
        $link = $this->client->paymentLinks->create($parameters);
        return [
            'id' => (string)$link->id,
            'url' => (string)$link->getCheckoutUrl(),
            'mode' => (string)$link->mode,
        ];
    }

    public function paymentsForLink(string $linkId): array
    {
        $ids = [];
        foreach ($this->client->paymentLinkPayments->iteratorForId($linkId) as $payment) {
            $ids[] = (string)$payment->id;
            if (count($ids) >= 250) { break; }
        }
        return $ids;
    }

    public function payment(string $paymentId, string $linkId): array
    {
        $payment = $this->client->payments->get($paymentId);
        $belongs = false;
        $checked = 0;
        foreach ($this->client->paymentLinkPayments->iteratorForId($linkId) as $linkedPayment) {
            if ((string)$linkedPayment->id === $paymentId) {
                $belongs = true;
                break;
            }
            if (++$checked >= 250) {
                break;
            }
        }
        return [
            'id' => (string)$payment->id,
            'status' => (string)$payment->status,
            'belongs_to_link' => $belongs,
            'currency' => (string)$payment->amount->currency,
            'amount' => (string)$payment->amount->value,
            'paid_at' => (string)($payment->paidAt ?? ''),
        ];
    }
}
