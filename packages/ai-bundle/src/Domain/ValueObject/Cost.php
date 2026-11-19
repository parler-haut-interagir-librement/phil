<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\ValueObject;

use Phil\AiBundle\Domain\Exception\InvalidAiValue;

/**
 * What a task cost, in millionths of a currency unit — 1 500 000 micros are 1.50 — and the ISO 4217 code of that
 * currency.
 */
final readonly class Cost
{
    /**
     * @throws InvalidAiValue
     */
    public function __construct(
        public int $micros,
        public string $currency,
    ) {
        if ($micros < 0) {
            throw InvalidAiValue::because(\sprintf('A cost is zero or more; got %d micros.', $micros));
        }

        self::assertCurrency($currency);
    }

    /**
     * @throws InvalidAiValue when the code is not three uppercase letters
     */
    public static function assertCurrency(string $currency): void
    {
        if (1 !== preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw InvalidAiValue::because(\sprintf('"%s" is not a currency code: three uppercase letters.', $currency));
        }
    }
}
