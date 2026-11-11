<?php

declare(strict_types=1);

namespace Phil\Passwords\Exception;

use InvalidArgumentException;

/**
 * A policy of generation that cannot be honoured: a length or a number of words out of bounds, no character set, a
 * separator that would merge the words.
 */
final class InvalidPolicy extends InvalidArgumentException implements PasswordsException
{
}
