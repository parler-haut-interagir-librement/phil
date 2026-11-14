<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Exception;

use Throwable;

/**
 * Every exception the bundle throws, to catch them all at once.
 */
interface OnboardingException extends Throwable
{
}
