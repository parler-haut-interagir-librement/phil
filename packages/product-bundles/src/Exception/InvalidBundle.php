<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Exception;

use InvalidArgumentException;

/**
 * A SKU, a quantity, a setting or a stored row outside what the library accepts.
 */
final class InvalidBundle extends InvalidArgumentException implements ProductBundlesException
{
    public static function because(string $field, string $rule): self
    {
        return new self(\sprintf('The %s %s.', $field, $rule));
    }
}
