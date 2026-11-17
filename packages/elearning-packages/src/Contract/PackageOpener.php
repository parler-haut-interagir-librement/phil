<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Contract;

use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;

/**
 * Opens a package for reading. `ZipPackageOpener` opens ZIP archives; another implementation opens a package kept
 * elsewhere, as long as the archive it returns keeps every path inside the package and every read within the limits.
 */
interface PackageOpener
{
    /**
     * @throws PackageRefused when the file is not a package, or one past the limits
     */
    public function open(string $path, PackageLimits $limits): PackageArchive;
}
