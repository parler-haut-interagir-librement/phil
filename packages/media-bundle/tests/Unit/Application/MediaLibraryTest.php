<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\NoMediaReferences;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

#[CoversClass(MediaLibrary::class)]
#[CoversClass(DoctrineMediaRepository::class)]
#[CoversClass(NoMediaReferences::class)]
final class MediaLibraryTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineMediaRepository $repository;

    private FilesystemStorage $storage;

    private string $directory;

    private MediaLibrary $library;

    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineMediaRepository($this->entityManager);
        $this->directory = Images::directory();
        $this->storage = new FilesystemStorage($this->directory);
        $clock = new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-04 12:00 UTC');
            }
        };
        $this->library = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), $clock, ['thumb' => ImageFormat::of('thumb', 100, 100)]);
    }

    public function testAnImageIsUploadedRenderedReplacedAndDeleted(): void
    {
        $collection = MediaCollection::create('events', 'Événements');
        $this->repository->save($collection);
        $media = $this->library->upload(Images::file(Images::png(400, 200)), 'fête.png', $collection);
        $this->entityManager->clear();
        $stored = $this->repository->get($media->id);

        self::assertNotNull($stored);
        self::assertSame(['fête.png', 'image/png', 400, 200, '2026-11-04T12:00:00+00:00', 'events'], [$stored->file()->filename, $stored->file()->mimeType, $stored->file()->width, $stored->file()->height, $stored->updatedAt()->format('c'), $stored->collection()?->key]);
        self::assertSame(Images::png(400, 200), $this->library->original($stored));
        self::assertSame([$media->id], array_map(static fn (Media $one): string => $one->id, $this->repository->inCollection($this->repository->collection('events'))));
        self::assertSame([], $this->repository->inCollection(null));
        self::assertNull($this->repository->collection('none'));

        $thumb = $this->library->rendered($stored, 'thumb', 'webp');
        self::assertSame('WEBP', substr($thumb, 8, 4));
        self::assertFileExists($this->directory.'/formats/'.$media->id.'/1/thumb-50-50.webp');
        file_put_contents($this->directory.'/formats/'.$media->id.'/1/thumb-50-50.webp', 'kept');
        self::assertSame('kept', $this->library->rendered($stored, 'thumb', 'webp'));
        $stored->focus(0.0, 0.25);
        self::assertSame("\x89PNG", substr($this->library->rendered($stored, 'thumb', 'png'), 0, 4));
        self::assertFileExists($this->directory.'/formats/'.$media->id.'/1/thumb-0-25.png');

        $this->library->replace($stored, Images::file(Images::png(40, 20)), 'fête-2.png');
        self::assertDirectoryDoesNotExist($this->directory.'/formats/'.$media->id.'/1');
        $this->entityManager->clear();
        $replaced = $this->repository->get($media->id);
        self::assertNotNull($replaced);
        self::assertSame([2, 40, Images::png(40, 20)], [$replaced->version(), $replaced->file()->width, $this->library->original($replaced)]);
        self::assertSame([1], array_map(static fn (MediaVersion $version): int => $version->number, $this->repository->versions($replaced)));

        $this->library->delete($replaced);
        $this->entityManager->clear();
        self::assertNull($this->repository->get($media->id));
        self::assertDirectoryDoesNotExist($this->directory.'/originals/'.$media->id);
        self::assertDirectoryDoesNotExist($this->directory.'/formats/'.$media->id);
        self::assertSame(['thumb'], $this->library->formats());
    }

    public function testADocumentIsKeptButNotRendered(): void
    {
        $media = $this->library->upload(Images::file('%PDF-1.4 a document'), 'rapport.pdf');

        self::assertSame(['application/pdf', null, null], [$media->file()->mimeType, $media->file()->width, $media->file()->height]);
        self::assertSame('%PDF-1.4 a document', $this->library->original($media));

        foreach ([
            [fn (): string => $this->library->rendered($media, 'thumb', 'webp'), $media->id.' is not an image.'],
            [fn (): string => $this->library->rendered($media, 'poster', 'webp'), 'There is no format "poster".'],
            [fn (): string => $this->library->rendered($media, 'thumb', 'gif'), 'An image is rendered as webp, avif, jpeg, png; not as "gif".'],
            [fn (): Media => $this->library->upload($this->directory.'/missing.png', 'missing.png'), 'The file "missing.png" cannot be read.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame($message, $invalidMedia->getMessage());
            }
        }

        $this->storage->deletePrefix('originals/'.$media->id.'/');
        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('The file of '.$media->id.' is missing.');

        $this->library->original($media);
    }

    public function testEachMediumKeepsItsOwnFiles(): void
    {
        $first = $this->library->upload(Images::file(Images::png(40, 20)), 'a.png');
        $second = $this->library->upload(Images::file(Images::png(40, 20)), 'b.png');
        $this->library->rendered($second, 'thumb', 'png');

        $this->library->delete($first);

        self::assertSame(Images::png(40, 20), $this->library->original($second));
        self::assertFileExists($this->directory.'/formats/'.$second->id.'/1/thumb-50-50.png');
        self::assertDirectoryExists($this->directory.'/originals');
        self::assertDirectoryExists($this->directory.'/formats');
    }

    public function testNarrowerWidthsAndTheOriginalAreRenderedOnDemand(): void
    {
        $library = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), new NativeClock(), ['wide' => ImageFormat::of('wide', 300, 100, widths: [150, 600])], ImageFormat::original(70, [100]));
        $media = $library->upload(Images::file(Images::png(400, 200)), 'a.png');

        self::assertSame([150, 50], \array_slice((array) getimagesizefromstring($library->rendered($media, 'wide', 'png', 150)), 0, 2));
        self::assertFileExists($this->directory.'/formats/'.$media->id.'/1/wide-50-50@150.png');
        self::assertSame([300, 100], \array_slice((array) getimagesizefromstring($library->rendered($media, 'wide', 'png', 600)), 0, 2));
        self::assertSame([400, 200], \array_slice((array) getimagesizefromstring($library->rendered($media, 'original', 'png')), 0, 2));
        self::assertSame('WEBP', substr($library->rendered($media, 'original', 'webp'), 8, 4));
        self::assertFileExists($this->directory.'/formats/'.$media->id.'/1/original-50-50.webp');
        self::assertSame([100, 50], \array_slice((array) getimagesizefromstring($library->rendered($media, 'original', 'png', 100)), 0, 2));
        self::assertSame(['original', 70], [$library->format('original')->name, $library->format('original')->quality]);
        self::assertSame([80, []], [$this->library->format('original')->quality, $this->library->format('original')->widths]);
        self::assertSame('wide', $library->format('wide')->name);

        foreach ([[static fn (): string => $library->rendered($media, 'wide', 'png', 200), 'The format "wide" is not rendered 200 pixels wide.'], [static fn (): ImageFormat => $library->format('none'), 'There is no format "none".']] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame($message, $invalidMedia->getMessage());
            }
        }
    }

    public function testTheFocalPointNamesTheRenderedFile(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'a.png');
        $media->focus(0.125, 0.874);

        $this->library->rendered($media, 'thumb', 'png');
        $media->focus(0.874, 0.125);
        $this->library->rendered($media, 'thumb', 'png');

        self::assertFileExists($this->directory.'/formats/'.$media->id.'/1/thumb-13-87.png');
        self::assertFileExists($this->directory.'/formats/'.$media->id.'/1/thumb-87-13.png');
    }

    public function testMovingTheFocalPointDeletesTheRenderingsAroundTheOldOne(): void
    {
        $media = $this->library->upload(Images::file(Images::png(40, 20)), 'a.png');
        $this->library->rendered($media, 'thumb', 'png');
        $kept = $this->directory.'/formats/'.$media->id.'/1/thumb-50-50.png';

        $this->library->focus($media, 0.501, 0.499);
        self::assertFileExists($kept);

        $this->library->focus($media, 0.2, 0.3);
        self::assertFileDoesNotExist($kept);
        $this->entityManager->clear();
        self::assertSame([0.2, 0.3], $this->repository->get($media->id)?->focalPoint());

        $stored = $this->repository->get($media->id);
        self::assertNotNull($stored);
        $this->library->rendered($stored, 'thumb', 'png');
        self::assertFileExists($this->directory.'/formats/'.$media->id.'/1/thumb-20-30.png');

        $this->library->focus($stored, 0.204, 0.296);
        $this->entityManager->clear();
        self::assertSame([0.204, 0.296], $this->repository->get($media->id)?->focalPoint());
        self::assertFileExists($this->directory.'/formats/'.$media->id.'/1/thumb-20-30.png');
    }

    public function testACollectionIsListedNewestFirstAPageAtATime(): void
    {
        $collection = MediaCollection::create('events', 'Événements');
        $this->repository->save($collection);
        $ids = [];

        foreach (['2026-11-01', '2026-11-03', '2026-11-02'] as $day) {
            $library = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), new readonly class($day) implements ClockInterface {
                public function __construct(
                    private string $day,
                ) {
                }

                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable($this->day.' UTC');
                }
            });
            $ids[$day] = $library->upload(Images::file('x'), $day.'.txt', $collection)->id;
        }

        $sameDay = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-02 UTC');
            }
        })->upload(Images::file('x'), 'same.txt', $collection)->id;
        $tie = [$sameDay, $ids['2026-11-02']];
        rsort($tie);

        self::assertSame([$ids['2026-11-03'], ...$tie, $ids['2026-11-01']], $this->ids($this->repository->inCollection($collection)));
        self::assertSame([$tie[1], $ids['2026-11-01']], $this->ids($this->repository->inCollection($collection, 2, 2)));
        self::assertSame([$ids['2026-11-03']], $this->ids($this->repository->inCollection($collection, 1)));
        self::assertCount(4, $this->repository->inCollection($collection, 51));

        for ($i = 0; $i < 47; ++$i) {
            $this->library->upload(Images::file('x'), $i.'.txt', $collection);
        }

        self::assertCount(50, $this->repository->inCollection($collection));
        self::assertCount(1, $this->repository->inCollection($collection, offset: 50));

        $this->repository->save(MediaCollection::create('books', 'Livres'), MediaCollection::create('azure', 'Livres'), MediaCollection::create('art', 'Arts'), MediaCollection::create('zoo', 'Animaux'));
        self::assertSame(['zoo', 'art', 'azure', 'books', 'events'], array_map(static fn (MediaCollection $collection): string => $collection->key, $this->repository->collections()));
    }

    public function testVersionsAreListedNewestFirst(): void
    {
        $other = $this->library->upload(Images::file('other'), 'b.txt');
        $this->library->replace($other, Images::file('again'), 'b.txt');
        $media = $this->library->upload(Images::file('one'), 'a.txt');
        $this->library->replace($media, Images::file('two'), 'a.txt');
        $this->library->replace($media, Images::file('three'), 'a.txt');

        self::assertSame([2, 1], array_map(static fn (MediaVersion $version): int => $version->number, $this->repository->versions($media)));
    }

    public function testWithoutAClockItIsNow(): void
    {
        $media = new MediaLibrary($this->repository, $this->storage, new GdImageProcessor(), new NativeClock())->upload(Images::file('x'), 'a.txt');

        self::assertEqualsWithDelta(time(), $media->updatedAt()->getTimestamp(), 5);
        self::assertSame(['image/webp', 'image/avif', 'image/jpeg', 'image/png'], array_values(MediaLibrary::TYPES));
    }

    /**
     * @param list<Media> $media
     *
     * @return list<string>
     */
    private function ids(array $media): array
    {
        return array_map(static fn (Media $one): string => $one->id, $media);
    }
}
