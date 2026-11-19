<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Repository\VenueRepository;

/**
 * The venues in `phil_calendar_venue`. Removing one leaves its events without a venue.
 */
final readonly class DoctrineVenueRepository implements VenueRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Venue $venue): void
    {
        $this->entityManager->persist($venue);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Venue $venue): void
    {
        // The foreign key sets the events' venue to null where the database enforces it; this does it everywhere.
        foreach ($this->entityManager->getRepository(Event::class)->findBy(['venue' => $venue]) as $event) {
            $event->placeAt(null);
        }

        $this->entityManager->remove($venue);
        $this->entityManager->flush();
    }

    #[Override]
    public function get(string $id): ?Venue
    {
        return $this->entityManager->find(Venue::class, $id);
    }

    #[Override]
    public function all(): array
    {
        /** @var list<Venue> */
        return $this->entityManager->getRepository(Venue::class)->findBy([], ['name' => 'ASC', 'room' => 'ASC']);
    }

    #[Override]
    public function ofListing(string $kind, string $reference): ?Venue
    {
        return $this->entityManager->getRepository(Venue::class)->findOneBy(['listing' => $kind.':'.$reference]);
    }
}
