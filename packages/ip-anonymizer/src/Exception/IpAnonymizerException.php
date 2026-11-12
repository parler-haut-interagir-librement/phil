<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Exception;

use Throwable;

/**
 * Every exception of the library. None of their messages repeats the address it was given: an address is personal
 * data, and an exception message ends up in logs.
 */
interface IpAnonymizerException extends Throwable
{
}
