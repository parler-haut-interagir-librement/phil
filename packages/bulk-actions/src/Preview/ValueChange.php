<?php

declare(strict_types=1);

namespace Phil\BulkActions\Preview;

/**
 * One field of a sampled item: its value now, and the value the change sets.
 */
final readonly class ValueChange
{
    /**
     * @param scalar|list<scalar|null>|null $new
     */
    public function __construct(
        public string $field,
        public bool|float|int|string|null $old,
        public bool|float|int|string|array|null $new,
    ) {
    }

    /**
     * Whether the change gives the field another value.
     */
    public function changes(): bool
    {
        return $this->old !== $this->new;
    }
}
