<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\TrashBundle\Domain\Model\TrashItem;
use Phil\TrashBundle\Domain\Repository\TrashItemRepository;

/**
 * The trash with Doctrine. Times are stored in UTC: the moment of a purge is asked for in UTC too.
 */
final readonly class DoctrineTrashItemRepository implements TrashItemRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(TrashItem $item): void
    {
        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(TrashItem $item): void
    {
        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    #[Override]
    public function get(string $id): ?TrashItem
    {
        return $this->entityManager->find(TrashItem::class, $id);
    }

    #[Override]
    public function list(?string $resourceKey = null, int $limit = 50, int $offset = 0): array
    {
        return $this->entityManager->getRepository(TrashItem::class)->findBy(null === $resourceKey ? [] : ['resourceKey' => $resourceKey], ['trashedAt' => 'DESC', 'id' => 'DESC'], $limit, $offset);
    }

    #[Override]
    public function expired(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<TrashItem> */
        return $this->entityManager->createQueryBuilder()
            ->select('item')
            ->from(TrashItem::class, 'item')
            ->where('item.purgeAfter <= :now')
            ->orderBy('item.purgeAfter', 'ASC')
            ->addOrderBy('item.id', 'ASC')
            ->setParameter('now', $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
