<?php

declare(strict_types=1);

namespace Phil\ElearningPackages;

/**
 * The formats of the packages the library reads: SCORM 1.2 and SCORM 2004, described by `imsmanifest.xml`, and cmi5,
 * described by `cmi5.xml`.
 */
enum PackageFormat: string
{
    case Scorm12 = 'scorm12';
    case Scorm2004 = 'scorm2004';
    case Cmi5 = 'cmi5';

    /**
     * The file at the root of a package that describes it in this format.
     */
    public function manifest(): string
    {
        return self::Cmi5 === $this ? 'cmi5.xml' : 'imsmanifest.xml';
    }
}
