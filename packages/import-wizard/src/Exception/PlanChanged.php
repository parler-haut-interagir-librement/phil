<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Exception;

use RuntimeException;

/**
 * The plan about to be written is not the one that was previewed, or not one the target writes: the file, the profile,
 * the mapping or the existing data changed in between, and the file is planned and previewed again.
 */
final class PlanChanged extends RuntimeException implements ImportWizardException
{
    public static function sincePreview(): self
    {
        return new self('The plan differs from the one previewed: preview the file again before writing it.');
    }

    public static function forTarget(string $planned, string $target): self
    {
        return new self(\sprintf('The plan imports "%s", the target writes "%s".', $planned, $target));
    }
}
