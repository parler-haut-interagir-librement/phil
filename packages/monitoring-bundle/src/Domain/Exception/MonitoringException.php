<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\Exception;

use Throwable;

/**
 * Every exception of the bundle: catch this one to catch them all.
 */
interface MonitoringException extends Throwable
{
}
