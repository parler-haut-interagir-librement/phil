<?php

declare(strict_types=1);

namespace Phil\Lti\Exception;

use Throwable;

/**
 * Every exception of the package: catch it to handle any failure of a launch, a message or a service call.
 */
interface LtiException extends Throwable
{
}
