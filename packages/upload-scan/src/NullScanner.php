<?php

declare(strict_types=1);

namespace Phil\UploadScan;

use Override;
use Phil\UploadScan\Contract\Scanner;
use Phil\UploadScan\Internal\Io;

/**
 * A scanner that scans nothing and finds every file clean: for tests, and for an environment without an antivirus
 * where scanning is knowingly turned off.
 */
final readonly class NullScanner implements Scanner
{
    #[Override]
    public function scanStream(mixed $stream): ScanResult
    {
        Io::stream($stream);

        return ScanResult::clean();
    }

    #[Override]
    public function scanPath(string $path): ScanResult
    {
        return ScanResult::clean();
    }
}
