<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Exception;

use RuntimeException;

/**
 * A package or a manifest the library refuses to read: an archive past its limits or holding an unsafe entry, a path
 * that would leave the package, or a manifest that is too large, declares a document type or an entity, is not well
 * formed, or describes no course. Its message says why in one sentence, naming a path or an identifier only once it
 * is checked, else a short part of it with its control characters replaced.
 */
final class PackageRefused extends RuntimeException implements ElearningPackagesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
