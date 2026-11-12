<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Exception;

use Throwable;

/**
 * Every exception phil/safe-http throws: one catch takes them all.
 */
interface SafeHttpException extends Throwable
{
}
