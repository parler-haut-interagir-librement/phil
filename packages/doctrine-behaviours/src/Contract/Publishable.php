<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use DateTimeImmutable;

/**
 * A record whose publication is a state of `symfony/workflow`, kept in the place the marking store reads and writes
 * — hence the two accessors in the workflow's own form —, with the dates `PublicationListener` stamps.
 */
interface Publishable
{
    public function getPublicationState(): string;

    /**
     * @param array<array-key, mixed> $context what the workflow gives along, unused by the record
     */
    public function setPublicationState(string $state, array $context = []): void;

    public function isPublished(): bool;

    public function publishedAt(): ?DateTimeImmutable;

    public function unpublishedAt(): ?DateTimeImmutable;

    /**
     * The record went public: keeps the date of a first publication or a date it was given, and forgets the date it
     * was withdrawn.
     */
    public function markPublished(DateTimeImmutable $at): void;

    public function markUnpublished(DateTimeImmutable $at): void;
}
