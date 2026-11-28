<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\WorkshopsBundle\Domain\Model\Workshop;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;

/**
 * Where the sessions are kept.
 */
interface SessionRepository
{
    public function get(string $id): ?WorkshopSession;

    public function save(WorkshopSession $session): void;

    /**
     * The sessions of a workshop, cancelled ones included, that end after a moment, at most `$limit`.
     *
     * @return list<WorkshopSession> by start
     */
    public function ofWorkshop(Workshop $workshop, DateTimeImmutable $after, int $limit): array;
}
