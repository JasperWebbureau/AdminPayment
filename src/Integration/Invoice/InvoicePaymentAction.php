<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Integration\Invoice;

use Flexgrid\Modules\AdminPayment\Application\Command\RegisterInvoicePaymentCommand;
use Flexgrid\Modules\AdminPayment\Exception\DuplicatePaymentReferenceException;
use Flexgrid\Response\AjaxResponse;
use Flexgrid\Utils\Request\Request;

final class InvoicePaymentAction
{
    public function createLink(): AjaxResponse
    {
        try {
            $publicId = $this->requestString(new Request(), 'invoice_public_id');
            $invoicePort = InvoicePaymentFactory::createInvoicePort();
            $context = $invoicePort->findPaymentDetailByPublicId(
                InvoicePaymentFactory::tenantContext()->getTenantId(), $publicId
            );
            if ($context === null) {
                throw new \DomainException('Factuur niet gevonden.');
            }
            InvoicePaymentFactory::createMollieLinks()->create(
                $context,
                rtrim((string)__DOMAIN__, '/') . '/api/AdminInvoicePayment/webhook'
            );
            $response = new AjaxResponse();
            $response->success = true;
            $response->redirect = rtrim((string)__DOMAIN__, '/') . '/Flexgrid/AdminInvoice/detail/' . rawurlencode($publicId);
            return $response;
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    public function register(): AjaxResponse
    {
        try {
            $request = new Request();
            $publicId = $this->requestString($request, 'invoice_public_id');
            $type = strtolower($this->requestString($request, 'payment_type'));
            $amount = $this->requestString($request, 'amount');
            if ($type === 'refund' && $amount !== '' && strpos($amount, '-') !== 0) {
                $amount = '-' . ltrim($amount, '+');
            }

            $invoicePort = InvoicePaymentFactory::createInvoicePort();
            $context = $invoicePort->findPaymentDetailByPublicId(
                InvoicePaymentFactory::tenantContext()->getTenantId(),
                $publicId
            );
            if ($context === null) {
                throw new \DomainException('Factuur niet gevonden.');
            }

            InvoicePaymentFactory::createRegisterInvoicePayment()->execute(
                new RegisterInvoicePaymentCommand(
                    $publicId,
                    $amount,
                    $context->getGrossTotal()->getCurrency()->getCode(),
                    $this->requestString($request, 'booked_on'),
                    $type,
                    $this->requestString($request, 'description'),
                    $this->requestString($request, 'reference'),
                    'manual'
                )
            );

            $context = $invoicePort->findPaymentDetailByPublicId(
                InvoicePaymentFactory::tenantContext()->getTenantId(),
                $publicId
            );
            if ($context === null) {
                throw new \RuntimeException('Factuurdetail kon na betaling niet worden vernieuwd.');
            }

            $response = new AjaxResponse();
            $response->success = true;
            $response->notifications = [
                '<div class="notification notification--success" fade="2400">Betaling geregistreerd.</div>',
            ];
            $response->setContainer(
                '[data-admin-payment-panel]',
                (new InvoiceDetailExtension())->render($context),
                true
            );

            return $response;
        } catch (\Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    private function requestString(Request $request, string $key): string
    {
        $value = $request->get($key, '');
        if (!is_string($value) && !is_int($value)) {
            return '';
        }

        return trim(strip_tags((string)$value));
    }

    private function errorResponse(\Throwable $throwable): AjaxResponse
    {
        $known = $throwable instanceof \InvalidArgumentException
            || $throwable instanceof \DomainException
            || $throwable instanceof \LogicException
            || $throwable instanceof DuplicatePaymentReferenceException;
        $message = $known ? $throwable->getMessage() : 'De betaling kon niet worden geregistreerd.';
        $response = new AjaxResponse();
        $response->success = false;
        $response->error = $message;
        $response->notifications = [
            '<div class="notification notification--error" fade="6000">'
            . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</div>',
        ];

        return $response;
    }
}
