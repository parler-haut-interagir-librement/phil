<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application\Exception;

use InvalidArgumentException;

/**
 * A rendition that the configuration does not name.
 */
final class UnknownRendition extends InvalidArgumentException
{
}
