<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Exception;

use Throwable;

/**
 * Implemented by every exception the bundle throws.
 */
interface UserSecretsException extends Throwable
{
}
