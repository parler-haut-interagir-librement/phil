<?php

declare(strict_types=1);

namespace Phil\Transcription;

/**
 * Where a transcriber sends a recording: to a service the site runs itself, or to the service of another company.
 */
enum Processing: string
{
    case Local = 'local';
    case Cloud = 'cloud';
}
