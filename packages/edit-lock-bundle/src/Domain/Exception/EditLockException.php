<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Domain\Exception;

use Throwable;

/**
 * Everything the bundle throws, catchable with a single `catch`.
 */
interface EditLockException extends Throwable
{
}
