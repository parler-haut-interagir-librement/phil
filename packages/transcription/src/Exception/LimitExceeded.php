<?php

declare(strict_types=1);

namespace Phil\Transcription\Exception;

use RuntimeException;

/**
 * A recording, a response, its segments or their text past the limits they were given.
 */
final class LimitExceeded extends RuntimeException implements TranscriptionException
{
}
