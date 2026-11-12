<?php

declare(strict_types=1);

namespace Phil\Idempotency\Exception;

use Throwable;

/**
 * What the library throws.
 *
 * An interface rather than a base class, so that each exception keeps the SPL parent that says what kind of
 * failure it is, while a caller can still catch everything from this library with a single `catch`.
 */
interface IdempotencyException extends Throwable
{
}
