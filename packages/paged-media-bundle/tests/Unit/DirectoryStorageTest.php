<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Override;
use Phil\PagedMediaBundle\Application\Exception\StorageFailed;
use Phil\PagedMediaBundle\Application\Output;
use Phil\PagedMediaBundle\Infrastructure\Storage\DirectoryStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(DirectoryStorage::class)]
#[CoversClass(StorageFailed::class)]
final class DirectoryStorageTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-paged-media-bundle-'.bin2hex(random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testAFileIsKeptUnderItsPathInsideTheDirectory(): void
    {
        $storage = new DirectoryStorage($this->directory.'/');

        $storage->store('tickets/2026/a12.pdf', new Output('%PDF-a', 'a12.pdf'));
        $storage->store('tickets\\2026\\b3.pdf', new Output('%PDF-b', 'b3.pdf'));
        $storage->store('top.pdf', new Output('%PDF-t', 'top.pdf'));
        $storage->store('tickets/2026/a12.pdf', new Output('%PDF-c', 'a12.pdf'));

        self::assertSame('%PDF-c', file_get_contents($this->directory.'/tickets/2026/a12.pdf'));
        self::assertSame('%PDF-b', file_get_contents($this->directory.'/tickets/2026/b3.pdf'));
        self::assertSame('%PDF-t', file_get_contents($this->directory.'/top.pdf'));
        self::assertSame(0o775 & ~umask(), fileperms($this->directory.'/tickets') & 0o777);
    }

    #[DataProvider('outsidePaths')]
    public function testAPathThatLeavesTheDirectoryIsRefused(string $path): void
    {
        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A rendered file is kept under a relative path inside the storage; "%s" is not one.', $path));

        new DirectoryStorage($this->directory)->store($path, new Output('%PDF', 'a.pdf'));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function outsidePaths(): iterable
    {
        yield [''];
        yield [' '];
        yield ['/etc/passwd'];
        yield ['\\server\\share.pdf'];
        yield ['../a.pdf'];
        yield ['tickets/../../a.pdf'];
        yield ['tickets\\..\\a.pdf'];
        yield ["a\0.pdf"];
    }

    public function testADirectoryThatCannotBeMadeIsReported(): void
    {
        mkdir($this->directory);
        file_put_contents($this->directory.'/tickets', 'a file, not a directory');

        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The directory "%s/tickets" cannot be made.', $this->directory));

        new DirectoryStorage($this->directory)->store('tickets/a.pdf', new Output('%PDF', 'a.pdf'));
    }

    public function testAFileThatCannotBeWrittenIsReported(): void
    {
        mkdir($this->directory.'/a.pdf', 0o777, true);

        $this->expectException(StorageFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The file "%s/a.pdf" cannot be written.', $this->directory));

        new DirectoryStorage($this->directory)->store('a.pdf', new Output('%PDF', 'a.pdf'));
    }
}
