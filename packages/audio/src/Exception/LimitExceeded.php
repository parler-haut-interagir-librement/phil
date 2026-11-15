<?php

declare(strict_types=1);

namespace Phil\Audio\Exception;

use RuntimeException;

/**
 * A file, a duration, an output or a run past the limits it was given.
 */
final class LimitExceeded extends RuntimeException implements AudioException
{
}
