<?php

declare(strict_types=1);

namespace Phil\Transcription\Exception;

use LogicException;

/**
 * A transcript published before a person reviewed it.
 */
final class NotReviewed extends LogicException implements TranscriptionException
{
}
