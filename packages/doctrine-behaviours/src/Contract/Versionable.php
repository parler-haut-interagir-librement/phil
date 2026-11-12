<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

/**
 * A record with real versions, numbered from one. The record itself is the version being worked on; `Versions` freezes
 * it as a snapshot when the application says so, and from then on that version is never edited: the next change of a
 * versioned field forks the next number, and the frozen one stays what it was.
 */
interface Versionable
{
    /**
     * The identifier of the record, known once it is inserted.
     */
    public function versionSubjectId(): string;

    public function versionNumber(): int;

    public function isVersionFrozen(): bool;

    public function markVersionFrozen(): void;

    /**
     * Moves on to the next number, not frozen.
     */
    public function forkVersion(): void;

    /**
     * What a version keeps, by mapped field: values JSON can hold, and no personal information it should not outlive.
     *
     * @return array<string, mixed>
     */
    public function versionedState(): array;

    /**
     * Puts the record back in a state `versionedState()` gave.
     *
     * @param array<string, mixed> $state
     */
    public function applyVersionedState(array $state): void;
}
