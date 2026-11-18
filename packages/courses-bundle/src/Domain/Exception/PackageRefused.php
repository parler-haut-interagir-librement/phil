<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Exception;

use Phil\ElearningPackages\Exception\PackageRefused as UnreadablePackage;
use RuntimeException;

/**
 * A course package the import refuses: an archive past its limits or with an unsafe entry, a manifest that is not
 * well formed, declares entities, or describes no course — what phil/elearning-packages refuses to read, kept as the
 * previous exception —, or a package that describes no activity the import can create.
 */
final class PackageRefused extends RuntimeException implements CoursesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * The refusal of the reader of packages, in the words it gave.
     */
    public static function from(UnreadablePackage $refused): self
    {
        return new self($refused->getMessage(), 0, $refused);
    }
}
