<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\WorkshopsBundle\Domain\Contract\SessionRepository;
use Phil\WorkshopsBundle\Domain\Model\Workshop;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;

/**
 * The sessions in an array.
 */
final class InMemorySessions implements SessionRepository
{
    /** @var array<string, WorkshopSession> */
    public array $sessions = [];

    public int $saves = 0;

    #[Override]
    public function get(string $id): ?WorkshopSession
    {
        return $this->sessions[$id] ?? null;
    }

    #[Override]
    public function save(WorkshopSession $session): void
    {
        $this->sessions[$session->id] = $session;
        ++$this->saves;
    }

    #[Override]
    public function ofWorkshop(Workshop $workshop, DateTimeImmutable $after, int $limit): array
    {
        $sessions = array_values(array_filter($this->sessions, static fn (WorkshopSession $session): bool => $session->workshop() === $workshop && $session->span()->until > $after->getTimestamp()));

        return \array_slice($sessions, 0, $limit);
    }
}
