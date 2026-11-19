<?php

declare(strict_types=1);

namespace Phil\MimeReader\Exception;

use InvalidArgumentException;

/**
 * Limits below their minimum.
 */
final class InvalidLimits extends InvalidArgumentException implements MimeReaderException
{
}
