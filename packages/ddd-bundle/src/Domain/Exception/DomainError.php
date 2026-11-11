<?php

declare(strict_types=1);

namespace Phil\DddBundle\Domain\Exception;

use Throwable;

/**
 * What the domain throws.
 *
 * It is an interface rather than a base class so that a context can give its errors whatever hierarchy suits
 * it while staying catchable with a single `catch` at the Application boundary.
 */
interface DomainError extends Throwable
{
}
