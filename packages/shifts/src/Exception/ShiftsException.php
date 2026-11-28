<?php

declare(strict_types=1);

namespace Phil\Shifts\Exception;

use Throwable;

/**
 * Every exception the library throws: a caller catches this one to catch them all.
 */
interface ShiftsException extends Throwable
{
}
