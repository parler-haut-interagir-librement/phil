<?php

declare(strict_types=1);

namespace Phil\Tax\Exception;

use InvalidArgumentException;

/**
 * A rate that cannot be one: a code a receipt cannot show, a share outside 0 to 100 %, or a percentage finer than
 * a part per million.
 */
final class InvalidTaxRate extends InvalidArgumentException implements TaxException
{
    public static function code(string $code): self
    {
        return new self(\sprintf('"%s" is not a tax rate code: up to 32 uppercase letters, digits, "_" or "-", starting with a letter.', $code));
    }

    public static function outOfRange(int $partsPerMillion): self
    {
        return new self(\sprintf('A tax rate lies between 0 and 1000000 parts per million; %d does not.', $partsPerMillion));
    }

    public static function notAPercent(string $percent): self
    {
        return new self(\sprintf('"%s" is not a percentage a tax rate holds: digits with up to 4 decimals after ".", from 0 to 100, are expected.', $percent));
    }
}
