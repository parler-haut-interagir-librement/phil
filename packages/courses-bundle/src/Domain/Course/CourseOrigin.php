<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Course;

use Phil\ElearningPackages\PackageFormat;

/**
 * Where a course comes from: written in the application, or imported from a package.
 */
enum CourseOrigin: string
{
    case Native = 'native';
    case Scorm12 = 'scorm12';
    case Scorm2004 = 'scorm2004';
    case Cmi5 = 'cmi5';

    /**
     * The origin of a course imported from a package of that format.
     */
    public static function of(PackageFormat $format): self
    {
        return match ($format) {
            PackageFormat::Scorm12 => self::Scorm12,
            PackageFormat::Scorm2004 => self::Scorm2004,
            PackageFormat::Cmi5 => self::Cmi5,
        };
    }
}
