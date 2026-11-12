<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Generator;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;

/**
 * A gallery, or a page of image emojis, reads its media by identifier: one `get()` each is one query each, where
 * `getMany()` reads them in one. SQLite in memory leaves out the network: a real database adds a round trip per query,
 * which widens the gap.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"media"})
 */
final class BatchReadBench
{
    private EntityManager $entityManager;

    private DoctrineMediaRepository $repository;

    /**
     * @var list<string>
     */
    private array $ids = [];

    public function __construct()
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineMediaRepository($this->entityManager);
    }

    /**
     * @param array{media: int} $params
     */
    public function setUp(array $params): void
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineMediaRepository($this->entityManager);
        $at = new DateTimeImmutable('2026-11-11 12:00 UTC');
        $media = [];

        for ($i = 0; $i < $params['media']; ++$i) {
            $media[] = Media::upload(FileInfo::of(\sprintf('emoji-%d.png', $i), 'image/png', 1_234, 64, 64), null, $at);
        }

        $this->repository->save(...$media);
        $this->ids = array_map(static fn (Media $medium): string => $medium->id, $media);
    }

    /**
     * One `get()` per medium, the identity map cleared first, as on a new request.
     *
     * @ParamProviders("pages")
     *
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchOneByOne(): void
    {
        $this->entityManager->clear();

        foreach ($this->ids as $id) {
            $this->repository->get($id);
        }
    }

    /**
     * One `getMany()` for the whole page, the identity map cleared first.
     *
     * @ParamProviders("pages")
     *
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function benchGetMany(): void
    {
        $this->entityManager->clear();
        $this->repository->getMany(...$this->ids);
    }

    /**
     * @return Generator<string, array{media: int}>
     */
    public function pages(): Generator
    {
        yield '12 media' => ['media' => 12];
        yield '48 media' => ['media' => 48];
        yield '200 media' => ['media' => 200];
    }
}
