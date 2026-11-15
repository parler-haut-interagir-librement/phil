<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\MediaBundle\Application\Contract\StreamingMediaStorage;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Application\MediaStreams;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Doctrine\DoctrineMediaRepository;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Tests\Unit\Fixtures\Database;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use Phil\MediaBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MediaLibrary::class)]
#[CoversClass(MediaStreams::class)]
final class StreamingTest extends TestCase
{
    public function testTheOriginalIsReadAsAStreamFromAStreamingStorage(): void
    {
        $directory = Images::directory();
        $library = $this->library(new FilesystemStorage($directory));
        $media = $library->upload(Images::file('first'), 'a.txt');
        $library->replace($media, Images::file('second version'), 'a.txt');

        $stream = $library->readOriginal($media);

        self::assertSame(['plainfile', 0], [stream_get_meta_data($stream)['wrapper_type'], ftell($stream)]);
        self::assertSame('second version', stream_get_contents($stream));
        fclose($stream);
    }

    public function testAStorageThatDoesNotStreamIsReadWholeIntoATemporaryStream(): void
    {
        $storage = $this->plainStorage();
        $storage->write('a', 'contents');

        $stream = MediaStreams::open($storage, 'a');
        self::assertNotNull($stream);
        self::assertSame(['php://temp/maxmemory:2097152', 0], [stream_get_meta_data($stream)['uri'] ?? null, ftell($stream)]);
        self::assertSame('contents', stream_get_contents($stream));
        fclose($stream);
        self::assertNull(MediaStreams::open($storage, 'missing'));

        $library = $this->library($storage);
        $media = $library->upload(Images::file('kept here'), 'a.txt');
        $stream = $library->readOriginal($media);
        self::assertSame('kept here', stream_get_contents($stream));
        fclose($stream);
    }

    public function testTheOriginalIsCopiedToALocalPath(): void
    {
        $library = $this->library(new FilesystemStorage(Images::directory()));
        $media = $library->upload(Images::file(str_repeat('0123456789', 100_000)), 'a.txt');
        $target = Images::directory().'/copy.bin';
        file_put_contents($target, str_repeat('x', 2_000_000));

        $library->copyOriginalTo($media, $target);

        self::assertSame(str_repeat('0123456789', 100_000), file_get_contents($target));
    }

    public function testAMissingFileIsAnError(): void
    {
        $directory = Images::directory();
        $library = $this->library(new FilesystemStorage($directory));
        $media = $library->upload(Images::file('x'), 'a.txt');
        unlink($directory.'/'.$media->storageKey());
        $target = Images::directory().'/copy.bin';

        foreach ([static fn (): mixed => $library->readOriginal($media), static fn () => $library->copyOriginalTo($media, $target)] as $operation) {
            try {
                $operation();
                self::fail('A missing file was read.');
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame(\sprintf('The file of %s is missing.', $media->id), $invalidMedia->getMessage());
            }
        }

        self::assertFileDoesNotExist($target);
    }

    public function testOnlyALocalPathThatCanBeOpenedIsATarget(): void
    {
        $library = $this->library(new FilesystemStorage(Images::directory()));
        $media = $library->upload(Images::file('x'), 'a.txt');

        foreach (['', 'php://memory', 'file:///tmp/copy', 'phar://a.phar/b'] as $path) {
            try {
                $library->copyOriginalTo($media, $path);
                self::fail($path);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame(\sprintf('"%s" is not a local path.', $path), $invalidMedia->getMessage());
            }
        }

        $path = Images::directory().'/missing/copy.bin';
        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The file of %s cannot be copied to "%s".', $media->id, $path));

        $library->copyOriginalTo($media, $path);
    }

    public function testACopyThatFailsIsRemovedAndEveryStreamClosed(): void
    {
        stream_wrapper_register('phil-media-failing', FailingStream::class);

        try {
            foreach (['false' => 'cannot be copied to', 'throw' => 'The stream broke.'] as $mode => $message) {
                FailingStream::$mode = $mode;
                $storage = $this->failingStorage();
                $library = $this->library($storage);
                $media = $library->upload(Images::file('x'), 'a.txt');
                $target = Images::directory().'/copy.bin';
                file_put_contents($target, 'kept before');

                try {
                    $library->copyOriginalTo($media, $target);
                    self::fail($mode);
                } catch (InvalidMedia|RuntimeException $exception) {
                    self::assertStringContainsString($message, $exception->getMessage());
                }

                self::assertFileDoesNotExist($target);
                self::assertCount(1, $storage->opened);
                self::assertFalse(\is_resource($storage->opened[0]), $mode);
            }
        } finally {
            stream_wrapper_unregister('phil-media-failing');
            FailingStream::$mode = 'false';
        }
    }

    private function library(MediaStorage $storage): MediaLibrary
    {
        return new MediaLibrary(new DoctrineMediaRepository(Database::entityManager()), $storage, new GdImageProcessor(), new MutableClock(new DateTimeImmutable('2026-11-14 12:00 UTC')));
    }

    /**
     * A storage whose streams fail after their first bytes; it keeps each stream it opened.
     *
     * @return StreamingMediaStorage&object{opened: list<resource>}
     */
    private function failingStorage(): StreamingMediaStorage
    {
        return new class implements StreamingMediaStorage {
            /**
             * @var list<resource>
             */
            public array $opened = [];

            #[Override]
            public function write(string $key, string $contents): void
            {
            }

            #[Override]
            public function read(string $key): string
            {
                return 'x';
            }

            /**
             * @return resource
             */
            #[Override]
            public function readStream(string $key): mixed
            {
                $stream = fopen('phil-media-failing://'.$key, 'r');
                \assert(false !== $stream);
                $this->opened[] = $stream;

                return $stream;
            }

            #[Override]
            public function deletePrefix(string $prefix): void
            {
            }
        };
    }

    private function plainStorage(): MediaStorage
    {
        return new class implements MediaStorage {
            /**
             * @var array<string, string>
             */
            private array $files = [];

            #[Override]
            public function write(string $key, string $contents): void
            {
                $this->files[$key] = $contents;
            }

            #[Override]
            public function read(string $key): ?string
            {
                return $this->files[$key] ?? null;
            }

            #[Override]
            public function deletePrefix(string $prefix): void
            {
                unset($this->files[$prefix]);
            }
        };
    }
}
