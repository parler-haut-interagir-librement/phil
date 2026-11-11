<?php

declare(strict_types=1);

namespace Phil\UploadScan;

/**
 * What becomes of a file the scanner could not scan — the scanner unreachable, too slow, or the file larger than it
 * reads: rejected, the safe default, or accepted and flagged, so that the application keeps it out of sight or scans it
 * again later.
 */
enum OnScanError: string
{
    case Reject = 'reject';
    case AcceptAndFlag = 'accept_and_flag';
}
