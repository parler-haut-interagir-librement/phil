<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value the ledger cannot hold: an empty SKU, a negative quantity, a commission above 100 %.
 */
final class InvalidValue extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * Checks an identifier the ledger stores in a 64-character column: a SKU, a location, a consignor, a reference.
     */
    public static function checkIdentifier(string $value, string $what): string
    {
        if ('' === trim($value) || mb_strlen($value) > 64 || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw self::because(\sprintf('A %s has 1 to 64 characters, none of them a control character; "%s" does not.', $what, mb_substr($value, 0, 80)));
        }

        return $value;
    }

    public static function checkQuantity(int $quantity, string $what = 'quantity'): int
    {
        if ($quantity < 1) {
            throw self::because(\sprintf('A %s is one unit or more; %d is not.', $what, $quantity));
        }

        return $quantity;
    }
}
