<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Domain\Exception;

use Phil\Resource\ResourceReference;
use RuntimeException;

/**
 * A resource cannot come back under a parent that is gone: restore it under another one.
 */
final class ParentMissing extends RuntimeException implements TrashException
{
    private function __construct(
        public readonly ResourceReference $resource,
        public readonly ResourceReference $parent,
    ) {
        parent::__construct(\sprintf('%s cannot be restored: its parent %s is gone; restore it under another parent.', $resource->toString(), $parent->toString()));
    }

    public static function of(ResourceReference $resource, ResourceReference $parent): self
    {
        return new self($resource, $parent);
    }
}
