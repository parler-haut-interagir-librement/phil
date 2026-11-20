<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Application\Import\XapiPackage;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\ElearningPackages\Contract\PackageArchive;

/**
 * Reads the description of an xAPI package — `tincan.xml` at the root of its archive — into the activities the import
 * creates. The import asks it for an archive that holds `tincan.xml` and neither `cmi5.xml` nor `imsmanifest.xml`;
 * without a reader, such a package is refused. An adapter wraps the reader of a library, within its bounds.
 */
interface XapiPackageReader
{
    public const string MANIFEST = 'tincan.xml';

    /**
     * @throws PackageRefused when the description is unsafe, malformed, or describes no launchable activity
     */
    public function read(PackageArchive $archive): XapiPackage;
}
