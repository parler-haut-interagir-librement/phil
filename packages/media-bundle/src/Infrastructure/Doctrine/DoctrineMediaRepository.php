<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;
use Phil\MediaBundle\Domain\Repository\MediaRepository;

/**
 * Media, their versions and collections with Doctrine. `getMany()` reads its media in one query, `IN` over at most
 * `BATCH` identifiers, and one more query per `BATCH` beyond: the number of bound parameters stays under the limits of
 * every database.
 */
final readonly class DoctrineMediaRepository implements MediaRepository
{
    public const int BATCH = 500;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Media|MediaVersion|MediaCollection ...$objects): void
    {
        foreach ($objects as $object) {
            $this->entityManager->persist($object);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Media|MediaCollection ...$objects): void
    {
        foreach ($objects as $object) {
            $this->entityManager->remove($object);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function get(string $id): ?Media
    {
        return $this->entityManager->find(Media::class, $id);
    }

    #[Override]
    public function getMany(string ...$ids): array
    {
        $ids = array_unique($ids);
        $found = [];

        foreach (array_chunk($ids, self::BATCH) as $batch) {
            foreach ($this->entityManager->getRepository(Media::class)->findBy(['id' => $batch]) as $media) {
                $found[$media->id] = $media;
            }
        }

        $ordered = [];

        foreach ($ids as $id) {
            if (isset($found[$id])) {
                $ordered[$id] = $found[$id];
            }
        }

        return $ordered;
    }

    #[Override]
    public function collection(string $key): ?MediaCollection
    {
        return $this->entityManager->getRepository(MediaCollection::class)->findOneBy(['key' => $key]);
    }

    #[Override]
    public function collections(): array
    {
        return $this->entityManager->getRepository(MediaCollection::class)->findBy([], ['name' => 'ASC', 'key' => 'ASC']);
    }

    #[Override]
    public function inCollection(?MediaCollection $collection, int $limit = 50, int $offset = 0): array
    {
        return $this->entityManager->getRepository(Media::class)->findBy(['collection' => $collection], ['updatedAt' => 'DESC', 'id' => 'DESC'], $limit, $offset);
    }

    #[Override]
    public function versions(Media $media): array
    {
        return $this->entityManager->getRepository(MediaVersion::class)->findBy(['media' => $media], ['number' => 'DESC']);
    }
}
