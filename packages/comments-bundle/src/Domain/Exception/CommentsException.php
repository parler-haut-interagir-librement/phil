<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Exception;

use Throwable;

/**
 * Everything the bundle throws, catchable with a single `catch`.
 */
interface CommentsException extends Throwable
{
}
