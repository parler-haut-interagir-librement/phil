<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Exception;

use Throwable;

/**
 * What this bundle throws.
 *
 * An interface rather than a base class, so every error the bundle raises can be caught with a single `catch`
 * at the application boundary while keeping the SPL type that code unaware of the bundle already catches.
 */
interface PrivacyError extends Throwable
{
}
