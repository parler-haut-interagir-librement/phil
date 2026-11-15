<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application\Exception;

use InvalidArgumentException;

/**
 * A video, a source, a text track or a viewing built with what the bundle refuses.
 */
final class InvalidVideo extends InvalidArgumentException
{
}
