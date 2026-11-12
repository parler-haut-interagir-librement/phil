<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\Exception;

use Throwable;

/**
 * Everything the bundle throws, catchable with a single `catch`.
 */
interface MediaException extends Throwable
{
}
