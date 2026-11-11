<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Application;

use Phil\Resource\ResourceReference;

/**
 * What a handler keeps of a resource it is about to remove: its identifier, a title to show in the trash, what it
 * needs to bring it back, and the parent it is under, if any.
 */
final readonly class Captured
{
    /**
     * @param array<string, mixed> $payload JSON-encodable
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $payload,
        public ?ResourceReference $parent = null,
    ) {
    }
}
