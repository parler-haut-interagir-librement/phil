<?php

declare(strict_types=1);

namespace Phil\Transcription;

/**
 * Whether the people heard in a recording agreed that it leaves the site for a transcription service of another
 * company. Without that consent, only a service the site runs itself transcribes it.
 */
enum RecordingConsent: string
{
    case LocalOnly = 'local_only';
    case Cloud = 'cloud';
}
