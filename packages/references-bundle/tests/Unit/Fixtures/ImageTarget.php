<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Unit\Fixtures;

use Phil\ReferencesBundle\Application\ReferenceTarget;
use Phil\Resource\ResourceReference;

final class ImageTarget implements ReferenceTarget
{
    /**
     * @param list<string> $existing
     */
    public function __construct(
        public array $existing = [],
    ) {
    }

    public function kind(): string
    {
        return 'image';
    }

    public function exists(string $id): bool
    {
        return \in_array($id, $this->existing, true);
    }

    public function referenceOf(object $resource): ?ResourceReference
    {
        return $resource instanceof Document && 'image' === $resource->kind ? ResourceReference::of('image', $resource->id) : null;
    }
}
