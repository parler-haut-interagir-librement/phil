<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Logging\Middleware;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

#[CoversClass(DoctrineMediaRepository::class)]
final class BatchReadTest extends TestCase
{
    public function testSeveralMediaAreReadInTheOrderAsked(): void
    {
        $entityManager = Database::entityManager();
        $repository = new DoctrineMediaRepository($entityManager);
        [$a, $b, $c] = [$this->media('a'), $this->media('b'), $this->media('c')];
        $repository->save($a, $b, $c);
        $entityManager->clear();

        $found = $repository->getMany($c->id, '00000000-0000-0000-0000-000000000000', $a->id, $c->id);

        self::assertSame([$c->id, $a->id], array_keys($found));
        self::assertSame(['c.png', 'a.png'], array_map(static fn (Media $media): string => $media->file()->filename, array_values($found)));
        self::assertSame([], $repository->getMany());

        $entityManager->clear();
        self::assertSame([$b->id], array_keys($repository->getMany($b->id)));
        self::assertSame(1, $entityManager->getUnitOfWork()->size());
    }

    public function testManyMediaAreReadInBatches(): void
    {
        $queries = new class extends AbstractLogger {
            public int $reads = 0;

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $sql = $context['sql'] ?? null;
                $this->reads += \is_string($sql) && str_contains($sql, 'SELECT') ? 1 : 0;
            }
        };
        $entityManager = Database::entityManager(middlewares: [new Middleware($queries)]);
        $repository = new DoctrineMediaRepository($entityManager);
        $media = [];

        for ($i = 0; $i < DoctrineMediaRepository::BATCH + 1; ++$i) {
            $media[] = $this->media('m'.$i);
        }

        $repository->save(...$media);
        $entityManager->clear();
        $queries->reads = 0;
        $ids = array_map(static fn (Media $medium): string => $medium->id, array_reverse($media));

        $found = $repository->getMany(...$ids);

        self::assertSame(501, DoctrineMediaRepository::BATCH + 1);
        self::assertSame($ids, array_keys($found));
        self::assertSame(2, $queries->reads);
        self::assertSame([$ids[0]], array_keys($repository->getMany($ids[0])));
        self::assertSame(3, $queries->reads);

        $queries->reads = 0;
        $batch = \array_slice($ids, 0, DoctrineMediaRepository::BATCH);
        self::assertCount(DoctrineMediaRepository::BATCH, $repository->getMany($ids[0], ...$batch));
        self::assertSame(1, $queries->reads);
    }

    private function media(string $name): Media
    {
        return Media::upload(FileInfo::of($name.'.png', 'image/png', 10, 4, 4), null, new DateTimeImmutable('2026-11-04 UTC'));
    }
}
