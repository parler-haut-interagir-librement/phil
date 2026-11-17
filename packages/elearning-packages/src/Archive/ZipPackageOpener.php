<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Archive;

use Override;
use Phil\ElearningPackages\Contract\PackageArchive;
use Phil\ElearningPackages\Contract\PackageOpener;
use Phil\ElearningPackages\PackageLimits;

/**
 * Opens the ZIP packages through PHP's zip extension.
 */
final readonly class ZipPackageOpener implements PackageOpener
{
    #[Override]
    public function open(string $path, PackageLimits $limits): PackageArchive
    {
        return ZipPackage::open($path, $limits);
    }
}
