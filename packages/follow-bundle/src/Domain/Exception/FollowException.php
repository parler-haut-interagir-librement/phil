<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\Exception;

use Throwable;

/**
 * What every exception of the bundle is, so that an application catches them all at once.
 */
interface FollowException extends Throwable
{
}
