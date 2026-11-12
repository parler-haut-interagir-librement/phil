<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Doctrine;

use Override;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;
use Phil\DirectoryBundle\Domain\Capability\RelatedCapable;

/**
 * The column of `RelatedCapable`: the related listings, in JSON.
 */
final class RelatedCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return RelatedCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [['fieldName' => 'related', 'type' => 'json', 'columnName' => 'related', 'nullable' => true]];
    }
}
