<?php

declare(strict_types=1);

namespace Phil\ElearningPackages;

/**
 * The formats of the packages the library reads: SCORM 1.2 and SCORM 2004, described by `imsmanifest.xml`; cmi5,
 * described by `cmi5.xml`; and an xAPI package that is neither, described by `tincan.xml`.
 */
enum PackageFormat: string
{
    case Scorm12 = 'scorm12';
    case Scorm2004 = 'scorm2004';
    case Cmi5 = 'cmi5';
    case Xapi = 'xapi';

    /**
     * The file at the root of a package that describes it in this format.
     */
    public function manifest(): string
    {
        return match ($this) {
            self::Cmi5 => 'cmi5.xml',
            self::Xapi => 'tincan.xml',
            self::Scorm12, self::Scorm2004 => 'imsmanifest.xml',
        };
    }
}
