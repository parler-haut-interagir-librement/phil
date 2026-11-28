<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Exception;

use RuntimeException;

/**
 * A component has no price in the bundle's currency, so the bundle cannot be priced: it is not sold rather than sold
 * at a guess.
 */
final class PriceUnknown extends RuntimeException implements ProductBundlesException
{
    public static function of(string $bundle, string $component, string $currency): self
    {
        return new self(\sprintf('The component "%s" of the bundle "%s" has no price in %s.', $component, $bundle, $currency));
    }
}
