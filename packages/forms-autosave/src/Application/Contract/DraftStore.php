<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Application\Contract;

use DateTimeImmutable;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;

/**
 * Where the drafts of the server are kept, each under its owner's hash and its key, written by compare-and-set: an
 * insertion fails when the draft exists, a replacement when another revision was saved meanwhile.
 */
interface DraftStore
{
    public function find(string $owner, DraftKey $key): ?Draft;

    /**
     * Keeps a first revision; false when the owner already has a draft under that key.
     */
    public function insert(Draft $draft): bool;

    /**
     * Puts the next revision in place of the one read; false when the stored revision is no longer the one read.
     */
    public function replace(Draft $read, Draft $next): bool;

    public function delete(string $owner, DraftKey $key): void;

    /**
     * Deletes the drafts expired at a moment, and tells how many.
     */
    public function purge(DateTimeImmutable $now): int;
}
