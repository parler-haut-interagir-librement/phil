<?php

declare(strict_types=1);

namespace Phil\Transcription\Exception;

use RuntimeException;

/**
 * A transcription service that cannot be reached, refuses the recording, or answers what is no transcript.
 */
final class TranscriptionFailed extends RuntimeException implements TranscriptionException
{
}
