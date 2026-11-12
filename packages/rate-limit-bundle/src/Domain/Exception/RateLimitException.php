<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Domain\Exception;

use Throwable;

/**
 * Every exception the bundle throws about its own rules, to catch them all at once.
 */
interface RateLimitException extends Throwable
{
}
