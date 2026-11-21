<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Exception;

use RuntimeException;

/**
 * The headers of a file leave required columns of the profile without a match: the mapping has to name them before the
 * file is planned.
 */
final class MappingIncomplete extends RuntimeException implements ImportWizardException
{
    /**
     * @param non-empty-list<string> $columns
     */
    public function __construct(public readonly array $columns)
    {
        parent::__construct('No header is mapped to the required columns: '.implode(', ', $columns).'.');
    }

    /**
     * @param non-empty-list<string> $columns
     */
    public static function missing(array $columns): self
    {
        return new self($columns);
    }
}
