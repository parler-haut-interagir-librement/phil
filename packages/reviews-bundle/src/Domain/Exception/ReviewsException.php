<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Exception;

use Throwable;

/**
 * What every failure of the bundle is, to catch them together.
 */
interface ReviewsException extends Throwable
{
}
