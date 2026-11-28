<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Contract;

use Phil\RelatedContent\RelatedSet;

/**
 * Where the neighbours computed at publication are kept, so that a page reads them without computing them.
 */
interface RelatedStore
{
    /**
     * Replaces the neighbours of an item.
     */
    public function save(RelatedSet $set): void;

    public function find(string $id): ?RelatedSet;

    public function remove(string $id): void;

    /**
     * The items whose kept neighbours include this one, to compute again when it changes or is retired.
     *
     * @return list<string>
     */
    public function pointingAt(string $id, int $limit): array;
}
