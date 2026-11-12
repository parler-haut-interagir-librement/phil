<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use Phil\DoctrineBehaviours\OwnerId;

/**
 * A record that belongs to someone, named by a stable identifier: `OwnerListener` gives it its owner as it is
 * persisted.
 */
interface Ownable
{
    public const string OWNER_FIELD = 'ownerId';

    public function ownerId(): ?OwnerId;

    /**
     * Gives a new record its owner, keeping one it was already given.
     */
    public function claimOwner(OwnerId $owner): void;

    /**
     * Hands the record to someone else, or to nobody.
     */
    public function transferOwner(?OwnerId $owner): void;
}
