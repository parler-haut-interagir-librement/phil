<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Contract;

/**
 * The neighbours an editor chose by hand for an item, in the order they are shown: they come before any other.
 */
interface Pins
{
    /**
     * @return list<string> item identifiers
     */
    public function pinned(string $id): array;
}
