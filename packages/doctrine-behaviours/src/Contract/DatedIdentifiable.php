<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use DateTimeImmutable;

/**
 * A record identified by a UUID v7, which starts with the time it was made: identifiers sort in creation order and
 * index well, and no format of our own has to be read back. `DatedIdentifierListener` gives one as it is persisted.
 */
interface DatedIdentifiable
{
    public function datedId(): ?string;

    public function assignDatedId(string $id): void;

    /**
     * The moment the identifier holds.
     */
    public function datedIdTime(): ?DateTimeImmutable;
}
