<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\ORM\EntityManager;
use Phil\MediaBundle\Application\DeleteBehaviour;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\MediaReference;
use Phil\MediaBundle\Domain\Exception\MediaInUse;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaReferences;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Doctrine\MediaType;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Page;
use Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Post;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

#[CoversClass(MediaType::class)]
#[CoversClass(DoctrineMediaReferences::class)]
#[CoversClass(MediaReference::class)]
#[CoversClass(MediaInUse::class)]
#[CoversClass(MediaLibrary::class)]
final class ReferencesTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineMediaRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager(records: true);
        $this->repository = new DoctrineMediaRepository($this->entityManager);
    }

    public function testTheColumnKeepsTheIdentifier(): void
    {
        $type = new MediaType();
        $platform = new SQLitePlatform();
        $media = $this->upload();
        $id = MediaId::fromString($media->id);

        self::assertSame('CHAR(36)', $type->getSQLDeclaration(['length' => 255], $platform));
        self::assertSame([null, $media->id, $media->id], [$type->convertToDatabaseValue(null, $platform), $type->convertToDatabaseValue($id, $platform), $type->convertToDatabaseValue($media, $platform)]);
        self::assertNull($type->convertToPHPValue(null, $platform));
        self::assertTrue($id->equals($type->convertToPHPValue($media->id.'  ', $platform) ?? MediaId::fromString('00000000-0000-0000-0000-000000000000')));
        self::assertSame('phil_media', MediaType::NAME);

        $this->expectException(InvalidType::class);
        $this->expectExceptionMessageMatches('{Expected one of the following types: null, .*MediaId, .*Media\.$}');
        $type->convertToDatabaseValue($media->id, $platform);
    }

    public function testAColumnThatHoldsSomethingElseIsNotRead(): void
    {
        foreach ([42, 'not-a-medium'] as $value) {
            try {
                new MediaType()->convertToPHPValue($value, new SQLitePlatform());
                self::fail('Read.');
            } catch (ValueNotConvertible $valueNotConvertible) {
                self::assertStringContainsString('phil_media', $valueNotConvertible->getMessage());
                self::assertSame(\is_string($value), $valueNotConvertible->getPrevious() instanceof Throwable);
            }
        }
    }

    public function testTheRecordsThatShowAMediumAreFound(): void
    {
        $media = $this->upload();
        $other = $this->upload();
        $id = MediaId::fromString($media->id);
        $this->entityManager->persist(new Post(1, $id, $id));
        $this->entityManager->persist(new Post(2, $id));
        $this->entityManager->persist(new Post(3, MediaId::fromString($other->id), $id));

        $page = new Page(1);
        $page->stamp = $id;
        $page->seo->image = $id;

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $references = new DoctrineMediaReferences($this->entityManager, DeleteBehaviour::SetNull, ['Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Page.stamp' => DeleteBehaviour::Keep]);
        $found = $references->of($id);
        usort($found, static fn (MediaReference $a, MediaReference $b): int => [$a->entity, $a->field] <=> [$b->entity, $b->field]);

        self::assertSame([
            'Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Page.seo.image (1) set_null',
            'Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Page.stamp (1) keep',
            'Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Post.banner (2) restrict',
            'Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Post.cover (2) set_null',
        ], array_map(static fn (MediaReference $reference): string => $reference->place().' '.$reference->behaviour->value, $found));
        self::assertSame([Post::class, 'cover', 2], [$found[3]->entity, $found[3]->field, $found[3]->count]);
        self::assertSame([], $references->of(MediaId::fromString('00000000-0000-0000-0000-000000000000')));

        $references->detach($id, $found[3]);
        $this->entityManager->clear();
        self::assertSame([null, null, null], array_map(fn (int $id): ?MediaId => $this->entityManager->find(Post::class, $id)?->cover, [1, 2, 3]));
        self::assertSame($media->id, $this->entityManager->find(Post::class, 1)?->banner->value);
        self::assertSame(DeleteBehaviour::Restrict, new DoctrineMediaReferences($this->entityManager)->of($id)[0]->behaviour);
    }

    public function testADeletionRespectsTheRecordsThatShowTheMedium(): void
    {
        $directory = Images::directory();
        $references = new DoctrineMediaReferences($this->entityManager, DeleteBehaviour::SetNull, ['Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Page.stamp' => DeleteBehaviour::Keep]);
        $library = new MediaLibrary($this->repository, new FilesystemStorage($directory), new GdImageProcessor(), new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC')), references: $references);
        $media = $library->upload(Images::file(Images::png(4, 4)), 'a.png');
        $kept = $library->upload(Images::file(Images::png(4, 4)), 'b.png');
        $id = MediaId::fromString($media->id);
        $this->entityManager->persist(new Post(1, MediaId::fromString($kept->id), $id));
        $page = new Page(1);
        $page->stamp = $id;

        $this->entityManager->persist($page);
        $this->entityManager->persist(new Post(2, $id));
        $this->entityManager->flush();

        self::assertCount(3, $library->usages($media));

        try {
            $library->delete($media);
            self::fail('Deleted.');
        } catch (MediaInUse $mediaInUse) {
            self::assertSame(['Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Post.banner (1)'], $mediaInUse->places);
            self::assertSame(\sprintf('The medium %s is still used by Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Post.banner (1).', $media->id), $mediaInUse->getMessage());
        }

        self::assertInstanceOf(Media::class, $this->repository->get($media->id));
        self::assertNotNull($this->entityManager->find(Post::class, 1)?->cover);
        $this->entityManager->remove($this->entityManager->find(Post::class, 2) ?? new stdClass());
        $this->entityManager->flush();

        $library->delete($media);
        $this->entityManager->clear();
        self::assertNull($this->repository->get($media->id));
        self::assertNull($this->entityManager->find(Post::class, 1)?->cover);
        self::assertSame($media->id, $this->entityManager->find(Page::class, 1)?->stamp?->value);
        self::assertFileDoesNotExist($directory.'/'.$media->storageKey());
        self::assertSame(['Phil\MediaBundle\Tests\Unit\Fixtures\Entity\Post.banner (1)'], array_map(static fn (MediaReference $reference): string => $reference->place(), $library->usages($kept)));
    }

    private function upload(): Media
    {
        return new MediaLibrary($this->repository, new FilesystemStorage(Images::directory()), new GdImageProcessor(), new MutableClock(new DateTimeImmutable('2026-11-06 12:00 UTC')))->upload(Images::file(Images::png(4, 4)), 'a.png');
    }
}
