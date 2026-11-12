<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use DateTimeImmutable;

/**
 * A record that can be switched off and back on: its row stays, `DeactivatedFilter` hides it from queries, and
 * `DeactivationListener` keeps the date it was switched off. This is no removal: a deactivated record is still
 * there, whole, for the screens that disable the filter.
 */
interface Deactivatable
{
    public function isActive(): bool;

    public function deactivatedAt(): ?DateTimeImmutable;

    public function deactivate(): void;

    public function reactivate(): void;

    /**
     * Sets the date of the deactivation, or forgets it with null: what the listener calls, and an import that keeps
     * an original date.
     */
    public function stampDeactivated(?DateTimeImmutable $at): void;
}
