<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Domain\Exception;

use Throwable;

/**
 * Everything the bundle throws, catchable with a single `catch`.
 */
interface EmbedException extends Throwable
{
}
