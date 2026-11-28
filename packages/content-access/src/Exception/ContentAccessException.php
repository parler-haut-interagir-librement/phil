<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Exception;

use Throwable;

/**
 * Every exception the library throws, for an application that catches them together.
 */
interface ContentAccessException extends Throwable
{
}
