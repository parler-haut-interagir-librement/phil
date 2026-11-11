<?php

declare(strict_types=1);

namespace Phil\Ujo;

/**
 * How much of a document the normalizer checks.
 *
 * - `Shape`, the default: what every saved document must be — its structure, the type and form of each value, the
 *   values a type requires present, the bounds a value must keep to be rendered, the limits of the normalizer. A draft
 *   is saved at this level.
 * - `Publication`: the shape, and what a document must hold to be read — the values a type requires filled in, and
 *   the bounds it sets on texts and lists. A document is checked at this level when it is published, scheduled or
 *   sent for review.
 */
enum Validation: string
{
    case Shape = 'shape';
    case Publication = 'publication';

    /**
     * Whether a document checked at this level has also passed the checks of the other one.
     */
    public function covers(self $level): bool
    {
        return self::Publication === $this || self::Shape === $level;
    }
}
