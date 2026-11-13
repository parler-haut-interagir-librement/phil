<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit;

use Override;
use Phil\EpubBundle\Application\Exception\StorageFailed;
use Phil\EpubBundle\Infrastructure\Media\MediaLibraryStorage;
use Phil\EpubBundle\Infrastructure\Storage\DirectoryStorage;
use Phil\EpubBundle\Tests\Unit\Fixture\Directory;
use Phil\EpubBundle\Tests\Unit\Fixture\MediaMemory;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(DirectoryStorage::class)]
#[CoversClass(MediaLibraryStorage::class)]
#[CoversClass(StorageFailed::class)]
final class StorageTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testKeepsABookInADirectory(): void
    {
        $directory = Directory::with([]);

        self::assertSame($directory.'/a/b/c.epub', new DirectoryStorage($directory)->store('a\b/c.epub', 'EPUB'));
        self::assertSame('EPUB', file_get_contents($directory.'/a/b/c.epub'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function outside(): iterable
    {
        yield 'empty' => [' '];
        yield 'absolute' => ['/etc/book.epub'];
        yield 'parent' => ['books/../../book.epub'];
        yield 'null byte' => ["book\0.epub"];
    }

    #[DataProvider('outside')]
    public function testRefusesAPathOutsideTheDirectory(string $path): void
    {
        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A book is kept under a relative path inside the storage; "%s" is not one.', $path));

        new DirectoryStorage('/srv/books')->store($path, 'EPUB');
    }

    public function testAFileThatCannotBeWritten(): void
    {
        $directory = Directory::with(['taken' => 'a file']);

        try {
            new DirectoryStorage($directory.'/taken')->store('book.epub', 'EPUB');
            self::fail('A file is in the way.');
        } catch (StorageFailed $storageFailed) {
            self::assertStringStartsWith(\sprintf('The book "%s/taken/book.epub" cannot be written: ', $directory), $storageFailed->getMessage());
            self::assertNotNull($storageFailed->getPrevious());
        }
    }

    public function testKeepsABookInTheMediaLibrary(): void
    {
        $memory = new MediaMemory();
        $temporary = Directory::with([]);
        $filesystem = new class extends Filesystem {
            /** @var list<string> */
            public array $directories = [];

            #[Override]
            public function tempnam(string $dir, string $prefix, string $suffix = ''): string
            {
                $this->directories[] = $dir;

                return parent::tempnam($dir, $prefix, $suffix);
            }
        };
        $id = new MediaLibraryStorage($memory->library(), $memory, temporaryDirectory: $temporary, filesystem: $filesystem)->store('stories/42.epub', 'EPUB');
        self::assertSame($temporary, $filesystem->directories[0] ?? null);
        $media = $memory->get($id);

        self::assertNotNull($media);
        self::assertSame('42.epub', $media->file()->filename);
        self::assertNull($media->collection());
        self::assertSame('EPUB', $memory->library()->original($media));
        self::assertSame([], glob($temporary.'/*'), 'The temporary file is removed.');
    }

    public function testKeepsABookInACollection(): void
    {
        $memory = new MediaMemory();
        $id = new MediaLibraryStorage($memory->library(), $memory, 'books')->store('stories\43.epub', 'EPUB');

        $media = $memory->get($id);

        self::assertNotNull($media);
        self::assertSame('books', $media->collection()?->key);
        self::assertSame('43.epub', $media->file()->filename);
    }

    public function testRefusesACollectionTheLibraryLacks(): void
    {
        $memory = new MediaMemory();

        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageIsOrContains('The media library has no collection "zines" to keep the books in.');

        new MediaLibraryStorage($memory->library(), $memory, 'zines')->store('42.epub', 'EPUB');
    }

    public function testAMediumTheLibraryRefuses(): void
    {
        $memory = new MediaMemory();

        try {
            new MediaLibraryStorage($memory->library(), $memory)->store('stories/'.str_repeat('a', 300).'.epub', 'EPUB');
            self::fail('A file name is at most 255 characters.');
        } catch (StorageFailed $storageFailed) {
            self::assertStringStartsWith('The book "stories/aaa', $storageFailed->getMessage());
            self::assertStringContainsString('.epub" cannot be kept in the media library: A file name is 1 to 255 characters', $storageFailed->getMessage());
            self::assertInstanceOf(InvalidMedia::class, $storageFailed->getPrevious());
        }
    }
}
