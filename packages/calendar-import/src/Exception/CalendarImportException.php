<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Exception;

use Throwable;

/**
 * Every exception of the library: one `catch` covers them all.
 */
interface CalendarImportException extends Throwable
{
}
