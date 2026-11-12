<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\AlmanacBundle\Domain\Model\StoredDate;
use Phil\AlmanacBundle\Domain\Repository\StoredDateRepository;

/**
 * The stored dates in `phil_almanac_date`. Saving and removing flush at once: an administrator's change is the whole
 * unit of work.
 *
 * `all()` reads the table once, then keeps what it read until a date is saved or removed here, or `reset()` is called:
 * the bundle tags the service `kernel.reset`, so a request, or a message in a worker, reads it once however many times
 * the almanac is built. A row changed by another process, or written without this repository, is seen at the next
 * reset.
 */
final class DoctrineStoredDateRepository implements StoredDateRepository
{
    /** @var list<StoredDate>|null */
    private ?array $all = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function all(): array
    {
        return $this->all ??= $this->entityManager->getRepository(StoredDate::class)->findBy([], ['key' => 'ASC']);
    }

    /**
     * Forgets the dates read, for the next `all()` to read the table again.
     */
    public function reset(): void
    {
        $this->all = null;
    }

    #[Override]
    public function ofKey(string $key): ?StoredDate
    {
        return $this->entityManager->getRepository(StoredDate::class)->findOneBy(['key' => $key]);
    }

    #[Override]
    public function save(StoredDate $date): void
    {
        $this->entityManager->persist($date);
        $this->entityManager->flush();
        $this->reset();
    }

    #[Override]
    public function remove(StoredDate $date): void
    {
        $this->entityManager->remove($date);
        $this->entityManager->flush();
        $this->reset();
    }
}
