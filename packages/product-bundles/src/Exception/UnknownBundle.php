<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Exception;

use RuntimeException;

/**
 * No bundle of that SKU is stored.
 */
final class UnknownBundle extends RuntimeException implements ProductBundlesException
{
    public static function sku(string $sku): self
    {
        return new self(\sprintf('No bundle has the SKU "%s".', $sku));
    }
}
