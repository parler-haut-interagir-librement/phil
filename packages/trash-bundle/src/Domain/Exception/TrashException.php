<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Domain\Exception;

use Throwable;

/**
 * Everything the trash throws, catchable with a single `catch`.
 */
interface TrashException extends Throwable
{
}
