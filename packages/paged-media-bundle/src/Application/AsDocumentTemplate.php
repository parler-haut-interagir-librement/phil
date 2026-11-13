<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application;

use Attribute;

/**
 * Registers a `DocumentTemplate` under a name.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsDocumentTemplate
{
    public function __construct(public string $name)
    {
    }
}
