<?php

declare(strict_types=1);

namespace Phil\MimeReader\Exception;

use InvalidArgumentException;

/**
 * A value given as a stream that is not an open stream, or a temporary stream that could not be opened.
 */
final class InvalidStream extends InvalidArgumentException implements MimeReaderException
{
}
