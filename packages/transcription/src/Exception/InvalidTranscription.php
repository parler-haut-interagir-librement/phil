<?php

declare(strict_types=1);

namespace Phil\Transcription\Exception;

use InvalidArgumentException;

/**
 * A value built with what the library refuses: a language that is no tag, a segment without text, an instant out of range.
 */
final class InvalidTranscription extends InvalidArgumentException implements TranscriptionException
{
}
