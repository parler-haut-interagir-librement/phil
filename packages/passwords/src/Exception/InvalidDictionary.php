<?php

declare(strict_types=1);

namespace Phil\Passwords\Exception;

use InvalidArgumentException;

/**
 * A dictionary of the strength estimator without a name, or with an entry that is empty or too long.
 */
final class InvalidDictionary extends InvalidArgumentException implements PasswordsException
{
}
