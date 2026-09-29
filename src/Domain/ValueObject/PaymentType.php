<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Domain\ValueObject;

final class PaymentType
{
    public const RECEIPT = 'receipt';
    public const REFUND = 'refund';

    /** @var string */ private $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));
        if (!in_array($value, self::values(), true)) {
            throw new \InvalidArgumentException('Ongeldig betalingstype.');
        }
        $this->value = $value;
    }

    public function getValue(): string { return $this->value; }

    /** @return string[] */
    public static function values(): array { return [self::RECEIPT, self::REFUND]; }
}
