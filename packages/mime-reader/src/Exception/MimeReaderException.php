<?php

declare(strict_types=1);

namespace Phil\MimeReader\Exception;

use Throwable;

/**
 * Every exception the package throws. Reading a message never throws: only a wrong configuration or stream does.
 */
interface MimeReaderException extends Throwable
{
}
