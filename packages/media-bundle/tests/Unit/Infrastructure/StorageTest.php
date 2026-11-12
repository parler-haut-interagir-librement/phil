<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Storage\FilesystemStorage;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilesystemStorage::class)]
final class StorageTest extends TestCase
{
    public function testFilesAreWrittenReadAndDeleted(): void
    {
        $directory = Images::directory();
        $storage = new FilesystemStorage($directory.'/');

        $storage->write('originals/a/1/photo.png', 'one');
        $storage->write('originals/a/2/photo.png', 'two');
        $storage->write('formats/a/1/thumb.webp', 'three');
        $storage->write('originals/b/1/photo.png', 'four');

        self::assertSame(['one', 'two', null], [$storage->read('originals/a/1/photo.png'), $storage->read('originals/a/2/photo.png'), $storage->read('originals/a/3/photo.png')]);
        self::assertFileExists($directory.'/originals/a/1/photo.png');

        $storage->deletePrefix('originals/a/');
        self::assertSame([null, null, 'three', 'four'], [$storage->read('originals/a/1/photo.png'), $storage->read('originals/a/2/photo.png'), $storage->read('formats/a/1/thumb.webp'), $storage->read('originals/b/1/photo.png')]);
        self::assertDirectoryDoesNotExist($directory.'/originals/a');
        $storage->deletePrefix('formats/a/1/thumb.webp');
        self::assertNull($storage->read('formats/a/1/thumb.webp'));
        $storage->deletePrefix('nothing/');
        self::assertDirectoryExists($directory.'/originals/b');
    }

    public function testAKeyStaysInTheDirectory(): void
    {
        $storage = new FilesystemStorage(Images::directory());

        foreach (['', '/etc/passwd', '../outside', 'a/../../outside'] as $key) {
            try {
                $storage->read($key);
                self::fail($key);
            } catch (InvalidMedia $invalidMedia) {
                self::assertSame(\sprintf('"%s" is not a storage key.', $key), $invalidMedia->getMessage());
            }
        }

        self::assertNull($storage->read('a..b'));
    }

    public function testADirectoryThatCannotBeCreatedIsAnError(): void
    {
        $file = Images::file('x');
        $storage = new FilesystemStorage($file);

        $this->expectException(InvalidMedia::class);
        $this->expectExceptionMessageIsOrContains('The directory of "a/b" cannot be created.');

        @$storage->write('a/b', 'x');
    }
}
