<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Exception;

use DomainException;

/**
 * A return the sold bundle does not allow: more bundles than are left to return, or fewer than one.
 */
final class ReturnRefused extends DomainException implements ProductBundlesException
{
    public static function because(string $bundle, string $reason): self
    {
        return new self(\sprintf('The return of the bundle "%s" is refused: %s.', $bundle, $reason));
    }
}
