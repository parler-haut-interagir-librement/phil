<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Exception;

use DomainException;

/**
 * A sale the components cannot fill: fewer bundles in stock than asked for.
 */
final class SaleRefused extends DomainException implements ProductBundlesException
{
    public static function outOfStock(string $bundle, int $asked, int $available, string $limitedBy): self
    {
        return new self(\sprintf('The bundle "%s" has %d in stock, limited by "%s"; %d asked.', $bundle, $available, $limitedBy, $asked));
    }
}
