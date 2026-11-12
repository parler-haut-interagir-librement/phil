<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

/**
 * An entity that knows who created it and who last updated it, by the identifier of the user: `BlameListener` blames
 * it as it is flushed.
 */
interface Blameable
{
    public function createdBy(): ?string;

    public function updatedBy(): ?string;

    /**
     * Blames a new entity, keeping a name it was already given.
     */
    public function blameCreated(string $who): void;

    public function blameUpdated(string $who): void;
}
