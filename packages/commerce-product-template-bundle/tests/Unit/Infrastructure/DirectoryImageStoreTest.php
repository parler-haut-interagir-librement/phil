<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Infrastructure;

use FilesystemIterator;
use Override;
use Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem\DirectoryImageStore;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

#[CoversClass(DirectoryImageStore::class)]
final class DirectoryImageStoreTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-product-template-images-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            $this->assertInstanceOf(SplFileInfo::class, $file);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->directory);
    }

    public function testAnImageIsWrittenWholeAndReadBack(): void
    {
        $store = new DirectoryImageStore($this->directory.'/');

        self::assertFalse($store->has('mockups/a/b.webp'));
        self::assertNull($store->read('mockups/a/b.webp'));

        $store->write('mockups/a/b.webp', 'bytes');
        $store->write('mockups/a/b.webp', 'other bytes');

        self::assertTrue($store->has('mockups/a/b.webp'));
        self::assertSame('other bytes', $store->read('mockups/a/b.webp'));
        self::assertSame('other bytes', file_get_contents($this->directory.'/mockups/a/b.webp'));
        self::assertSame(['b.webp'], $this->entries($this->directory.'/mockups/a'));
        self::assertSame(0o775 & ~umask(), fileperms($this->directory.'/mockups/a') & 0o777);
    }

    public function testItsDirectoriesAndFilesTakeTheModesGiven(): void
    {
        $store = new DirectoryImageStore($this->directory, 0o700, 0o600);

        $store->write('private/a/b.png', 'bytes');

        self::assertSame(0o700, fileperms($this->directory.'/private/a') & 0o777);
        self::assertSame(0o600, fileperms($this->directory.'/private/a/b.png') & 0o777);
    }

    public function testADirectoryIsNotAnImage(): void
    {
        mkdir($this->directory.'/blanks');
        $store = new DirectoryImageStore($this->directory);

        self::assertFalse($store->has('blanks'));
        self::assertNull($store->read('blanks'));
    }

    public function testAnImageThatCannotBeWrittenIsReported(): void
    {
        file_put_contents($this->directory.'/blanks', 'a file');

        $this->expectExceptionObject(new RuntimeException('The directory of the image blanks/a.png cannot be created.'));

        new DirectoryImageStore($this->directory)->write('blanks/a.png', 'x');
    }

    public function testAFileInThePlaceOfTheImageIsReported(): void
    {
        mkdir($this->directory.'/taken.png');

        $this->expectExceptionObject(new RuntimeException('The image taken.png cannot be written.'));

        try {
            new DirectoryImageStore($this->directory)->write('taken.png', 'x');
        } finally {
            self::assertSame(['taken.png'], $this->entries($this->directory));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function paths(): iterable
    {
        yield 'absolute' => ['/etc/passwd'];
        yield 'parent' => ['../a.png'];
        yield 'parent inside' => ['a/../../b.png'];
        yield 'current' => ['./a.png'];
        yield 'empty' => [''];
        yield 'a backslash' => ['a\\b.png'];
        yield 'too long' => [str_repeat('a', 513)];
        yield 'a line after' => ["a.png\n"];
    }

    #[DataProvider('paths')]
    public function testAPathThatCouldLeaveTheDirectoryIsRefused(string $path): void
    {
        $this->expectExceptionObject(InvalidValue::because(\sprintf('"%s" is not a path of the store of images.', $path)));

        new DirectoryImageStore($this->directory)->has($path);
    }

    public function testTheLongestPathIsAccepted(): void
    {
        self::assertFalse(new DirectoryImageStore($this->directory)->has(str_repeat('a', 255).'/'.str_repeat('b', 254).'/x'));
    }

    /**
     * @return list<string>
     */
    private function entries(string $directory): array
    {
        $entries = scandir($directory);
        self::assertIsArray($entries);

        return array_values(array_diff($entries, ['.', '..']));
    }
}
