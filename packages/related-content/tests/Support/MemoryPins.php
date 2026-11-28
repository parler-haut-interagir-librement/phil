<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Tests\Support;

use Phil\RelatedContent\Contract\Pins;

final readonly class MemoryPins implements Pins
{
    /**
     * @param array<string, list<string>> $pins
     */
    public function __construct(
        private array $pins,
    ) {
    }

    public function pinned(string $id): array
    {
        return $this->pins[$id] ?? [];
    }
}
