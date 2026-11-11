<?php

declare(strict_types=1);

namespace Phil\Passwords\Exception;

use InvalidArgumentException;

/**
 * A wordlist too short to give passphrases their entropy, or with a word that is empty, repeated or holds whitespace.
 */
final class InvalidWordlist extends InvalidArgumentException implements PasswordsException
{
}
