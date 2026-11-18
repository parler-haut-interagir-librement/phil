<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Exception;

use Throwable;

/**
 * What every exception of the bundle implements, to catch them all at once.
 */
interface OrganizationsException extends Throwable
{
}
