<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Domain\Exception;

use Throwable;

/**
 * Everything the bundle throws, catchable with a single `catch`.
 */
interface SearchException extends Throwable
{
}
