<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Exception;

use Throwable;

/**
 * What every exception of the bundle implements, to catch them all at once.
 */
interface EmailTemplatesException extends Throwable
{
}
