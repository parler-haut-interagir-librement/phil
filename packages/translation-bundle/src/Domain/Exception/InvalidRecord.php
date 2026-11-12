<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A row of the translation tables that cannot be built: a document reference out of its bounds, a row given a segment
 * of another key.
 */
final class InvalidRecord extends InvalidArgumentException implements TranslationBundleException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
