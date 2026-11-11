<?php

declare(strict_types=1);

namespace Phil\UploadScan;

/**
 * What a scan found: nothing, a threat, or nothing it could say, because the file was not scanned to its end.
 */
enum ScanStatus: string
{
    case Clean = 'clean';
    case Infected = 'infected';
    case Error = 'error';
}
