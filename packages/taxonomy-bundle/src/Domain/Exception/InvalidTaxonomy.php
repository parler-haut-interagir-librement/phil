<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Domain\Exception;

use InvalidArgumentException;

final class InvalidTaxonomy extends InvalidArgumentException implements TaxonomyException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
