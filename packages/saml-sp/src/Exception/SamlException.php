<?php

declare(strict_types=1);

namespace Phil\SamlSp\Exception;

use Throwable;

/**
 * Every exception of the library: a message, a signature, a key or metadata refused, or metadata out of reach. Its
 * message names what was refused, never a name identifier, an attribute value or a key.
 */
interface SamlException extends Throwable
{
}
