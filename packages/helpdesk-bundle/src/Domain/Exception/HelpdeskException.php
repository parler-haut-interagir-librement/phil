<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Domain\Exception;

use Throwable;

/**
 * What every failure of the help desk is, to catch them together.
 */
interface HelpdeskException extends Throwable
{
}
