<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use LogicException;

/**
 * The content classes declared to the bundle do not fit together: a root class that is not a content root,
 * a row class claimed by two roots, a row class no declared root names, a capability without its property.
 *
 * A mistake of the application's code or configuration, found when the container is built or the mapping
 * loaded, never a condition to recover from at run time.
 */
final class InvalidContentMapping extends LogicException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
