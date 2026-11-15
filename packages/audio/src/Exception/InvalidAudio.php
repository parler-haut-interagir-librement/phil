<?php

declare(strict_types=1);

namespace Phil\Audio\Exception;

use InvalidArgumentException;

/**
 * A value built with what the library refuses, or a document — peaks, the report of ffprobe — it cannot read.
 */
final class InvalidAudio extends InvalidArgumentException implements AudioException
{
}
