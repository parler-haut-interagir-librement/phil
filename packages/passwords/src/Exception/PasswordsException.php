<?php

declare(strict_types=1);

namespace Phil\Passwords\Exception;

use Throwable;

/**
 * Implemented by every exception of the library, so that an application catches them in one place.
 */
interface PasswordsException extends Throwable
{
}
