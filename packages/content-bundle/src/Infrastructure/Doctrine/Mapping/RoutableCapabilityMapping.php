<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Mapping;

use Override;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;

/**
 * `RoutableCapable`: the slug in a nullable `VARCHAR(255)`. No unique index, since every version and both
 * stages of a locale carry the same slug (see `RoutableCapable`).
 */
final class RoutableCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return RoutableCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            ['fieldName' => 'slug', 'type' => 'string', 'columnName' => 'slug', 'length' => 255, 'nullable' => true],
        ];
    }
}
