<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Override;
use Phil\UploadScan\Contract\Scanner;
use Phil\UploadScan\ScanResult;

/**
 * A scanner that finds every value infected without reading it, a stream or not.
 */
final class CondemningScanner implements Scanner
{
    #[Override]
    public function scanStream(mixed $stream): ScanResult
    {
        return ScanResult::infected('Test.Condemned');
    }

    #[Override]
    public function scanPath(string $path): ScanResult
    {
        return ScanResult::infected('Test.Condemned');
    }
}
