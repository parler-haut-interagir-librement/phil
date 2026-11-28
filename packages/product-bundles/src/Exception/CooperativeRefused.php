<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Exception;

use DomainException;

/**
 * A change a cooperative bundle does not allow in its state: a share changed after the first approval, an approval by
 * someone who does not take part, shares asked for before every participant approved.
 */
final class CooperativeRefused extends DomainException implements ProductBundlesException
{
    public static function because(string $bundle, string $reason): self
    {
        return new self(\sprintf('The cooperative bundle "%s" refuses the change: %s.', $bundle, $reason));
    }
}
