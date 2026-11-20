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
    case Xapi = 'xapi';

    /**
     * The origin of a course imported from a package of that format, named by the same value.
     */
    public static function of(PackageFormat $format): self
    {
        return self::from($format->value);
    }
}
