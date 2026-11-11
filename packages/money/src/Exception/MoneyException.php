<?php

declare(strict_types=1);

namespace Phil\Money\Exception;

use Throwable;

/**
 * Every exception the library throws, so that an application maps them in one place: each one is a mistake of the
 * caller or of its input, never a failure of the library.
 */
interface MoneyException extends Throwable
{
}
