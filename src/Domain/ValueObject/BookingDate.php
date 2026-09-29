<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Domain\ValueObject;

final class BookingDate
{
    /** @var string */ private $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new \InvalidArgumentException('Boekdatum moet een geldige datum in YYYY-MM-DD-formaat zijn.');
        }
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
