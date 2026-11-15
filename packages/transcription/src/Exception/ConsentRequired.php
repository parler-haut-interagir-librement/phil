<?php

declare(strict_types=1);

namespace Phil\Transcription\Exception;

use DomainException;

/**
 * A recording sent to a service outside the site without the consent of the people heard in it: refused before anything leaves.
 */
final class ConsentRequired extends DomainException implements TranscriptionException
{
}
