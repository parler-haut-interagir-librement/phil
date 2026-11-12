<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Storage\FlysystemStorage;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlysystemStorage::class)]
final class FlysystemStorageTest extends TestCase
{
    public function testFilesAreWrittenReadAndDeleted(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $storage = new FlysystemStorage($filesystem);

        $storage->write('originals/a/1/photo.png', 'one');
        $storage->write('originals/a/2/photo.png', 'two');
        $storage->write('formats/a/1/thumb.webp', 'three');
        $storage->write('originals/b/1/photo.png', 'four');

        self::assertSame(['one', 'two', null], [$storage->read('originals/a/1/photo.png'), $storage->read('originals/a/2/photo.png'), $storage->read('originals/a/3/photo.png')]);

        $storage->deletePrefix('originals/a/');
        self::assertSame([null, null, 'three', 'four'], [$storage->read('originals/a/1/photo.png'), $storage->read('originals/a/2/photo.png'), $storage->read('formats/a/1/thumb.webp'), $storage->read('originals/b/1/photo.png')]);
        $storage->deletePrefix('formats/a/1/thumb.webp');
        self::assertNull($storage->read('formats/a/1/thumb.webp'));
        $storage->deletePrefix('originals/b');
        self::assertFalse($filesystem->fileExists('originals/b/1/photo.png'));
        $storage->deletePrefix('nothing/');
    }

    public function testADirectoryOnTheServer(): void
    {
        $directory = Images::directory();
        $storage = new FlysystemStorage(new Filesystem(new LocalFilesystemAdapter($directory)));

        $storage->write('formats/a/1/thumb.webp', 'kept');
        self::assertSame('kept', file_get_contents($directory.'/formats/a/1/thumb.webp'));
        $storage->deletePrefix('formats/a/');
        self::assertDirectoryDoesNotExist($directory.'/formats/a');
        self::assertDirectoryExists($directory.'/formats');
    }

    public function testAKeyStaysInsideAndAFailureIsInvalidMedia(): void
    {
        $storage = new FlysystemStorage(new Filesystem(new InMemoryFilesystemAdapter()));

        foreach (['', '/etc/passwd', '../outside', 'a/../../outside'] as $key) {
            foreach ([static fn (): ?string => $storage->read($key), static fn () => $storage->write($key, 'x'), static fn () => $storage->deletePrefix($key)] as $operation) {
                try {
                    $operation();
                    self::fail($key);
                } catch (InvalidMedia $invalidMedia) {
                    self::assertSame(\sprintf('"%s" is not a storage key.', $key), $invalidMedia->getMessage());
                }
            }
        }

        self::assertNull($storage->read('a..b'));

        $file = Images::file('x');
        $broken = new FlysystemStorage(new Filesystem(new LocalFilesystemAdapter(\dirname($file))));

        try {
            $broken->write(basename($file).'/inside', 'x');
            self::fail('A file was written under a file.');
        } catch (InvalidMedia $invalidMedia) {
            self::assertStringStartsWith(\sprintf('The storage failed on "%s/inside": ', basename($file)), $invalidMedia->getMessage());
            self::assertNotNull($invalidMedia->getPrevious());
        }
    }
}
