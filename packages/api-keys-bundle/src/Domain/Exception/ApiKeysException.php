<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Exception;

use Throwable;

/**
 * What every exception of the bundle implements, to catch them all at once.
 */
interface ApiKeysException extends Throwable
{
}
