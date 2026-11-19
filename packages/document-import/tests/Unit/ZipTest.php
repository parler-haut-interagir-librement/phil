<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Override;
use Phil\DocumentImport\Exception\ExportFailed;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Internal\Archive;
use Phil\DocumentImport\Internal\Zip;
use Phil\DocumentImport\Limits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use ValueError;
use ZipArchive;

/**
 * Archives opened and written in memory where the PHP version has `ZipArchive::openString()`, through a temporary
 * file otherwise, with the same bytes and the same refusals either way, and no file left behind.
 */
#[CoversClass(Zip::class)]
#[CoversClass(Archive::class)]
final class ZipTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-zip-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        rmdir($this->directory);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function modes(): iterable
    {
        yield 'temporary file' => [false];

        if (Zip::inMemory()) {
            yield 'in memory' => [true];
        }
    }

    public function testTheModeFollowsThePhpVersion(): void
    {
        self::assertSame(method_exists(ZipArchive::class, 'openString'), Zip::inMemory());
    }

    #[DataProvider('modes')]
    public function testAnArchiveWrittenIsReadBack(bool $inMemory): void
    {
        $bytes = $this->archive($inMemory);

        self::assertStringStartsWith('PK', $bytes);
        self::assertSame(['mimetype' => 'text/plain', 'a.xml' => '<a/>'], Zip::read($bytes, $this->entries(...), $inMemory, $this->directory));
        self::assertSame(['.', '..'], scandir($this->directory));
    }

    #[RequiresMethod(ZipArchive::class, 'openString')]
    public function testBothModesWriteTheSameBytes(): void
    {
        self::assertSame($this->archive(false), $this->archive(true));
        self::assertSame(['mimetype' => 'text/plain', 'a.xml' => '<a/>'], Zip::read($this->archive(false), $this->entries(...), true));
        self::assertSame(['mimetype' => 'text/plain', 'a.xml' => '<a/>'], Zip::read($this->archive(true), $this->entries(...), false));
    }

    #[DataProvider('modes')]
    public function testBytesThatAreNoArchiveAreRefused(bool $inMemory): void
    {
        try {
            Zip::read('not a zip', $this->entries(...), $inMemory, $this->directory);
            self::fail('Bytes that are no archive are read.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('The file is not a ZIP archive.', $invalidDocument->getMessage());
        }

        self::assertSame(['.', '..'], scandir($this->directory));
    }

    #[DataProvider('modes')]
    public function testTheArchiveIsClosedAndTheFileRemovedWhenTheReadingFails(bool $inMemory): void
    {
        $opened = null;

        try {
            Zip::read($this->archive($inMemory), static function (ZipArchive $zip) use (&$opened): never {
                $opened = $zip;

                throw InvalidDocument::because('stop');
            }, $inMemory, $this->directory);
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('stop', $invalidDocument->getMessage());
        }

        self::assertInstanceOf(ZipArchive::class, $opened);
        self::assertSame(['.', '..'], scandir($this->directory));
        self::assertTrue($this->closed($opened));
    }

    #[DataProvider('modes')]
    public function testTheArchiveIsClosedWhenTheReadingSucceeds(bool $inMemory): void
    {
        $opened = Zip::read($this->archive($inMemory), static fn (ZipArchive $zip): ZipArchive => $zip, $inMemory, $this->directory);

        self::assertTrue($this->closed($opened));
    }

    #[RequiresMethod(ZipArchive::class, 'openString')]
    public function testAnEmptyArchiveCannotBeWrittenInMemory(): void
    {
        try {
            Zip::write(static function (ZipArchive $zip): void {
            }, true);
            self::fail('An empty archive is written.');
        } catch (ExportFailed $exportFailed) {
            self::assertSame('The archive cannot be written.', $exportFailed->getMessage());
        }
    }

    public function testTheArchiveIsWrittenInTheDirectoryGiven(): void
    {
        $seen = [];
        Zip::write(function (ZipArchive $zip) use (&$seen): void {
            $seen = scandir($this->directory);
            $zip->addFromString('a', 'b');
        }, false, $this->directory);

        self::assertIsArray($seen);
        self::assertCount(3, $seen);
        self::assertStringStartsWith('phil-export', $seen[2]);
        self::assertSame(['.', '..'], scandir($this->directory));
    }

    public function testTheArchiveIsReadFromAFileOfTheDirectoryGiven(): void
    {
        $seen = Zip::read($this->archive(false), fn (ZipArchive $zip): array|false => scandir($this->directory), false, $this->directory);

        self::assertIsArray($seen);
        self::assertCount(3, $seen);
        self::assertStringStartsWith('phil-import', $seen[2]);
    }

    public function testTheMemoryIsAskedForOnAnyPhpVersion(): void
    {
        $bytes = Zip::write(static function (ZipArchive $zip): void {
            $zip->addFromString('a.xml', '<a/>');
        }, true, $this->directory);

        self::assertStringStartsWith('PK', $bytes);
        self::assertSame(['a.xml' => '<a/>'], Zip::read($bytes, $this->entries(...), true, $this->directory));
        self::assertSame(['.', '..'], scandir($this->directory));
    }

    public function testAnArchiveOfThePhpVersionIsOpened(): void
    {
        $archive = Archive::open($this->archive(Zip::inMemory()), new Limits());

        self::assertSame(['mimetype', 'a.xml'], $archive->names());
        self::assertSame('<a/>', $archive->get('a.xml'));
    }

    private function closed(ZipArchive $zip): bool
    {
        try {
            $zip->count();
        } catch (ValueError) {
            return true;
        }

        return false;
    }

    private function archive(bool $inMemory): string
    {
        return Zip::write(static function (ZipArchive $zip): void {
            foreach (['mimetype' => 'text/plain', 'a.xml' => '<a/>'] as $name => $bytes) {
                $zip->addFromString($name, $bytes);
                $zip->setMtimeName($name, 315_532_800);
            }
        }, $inMemory);
    }

    /**
     * @return array<string, string>
     */
    private function entries(ZipArchive $zip): array
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $entries[(string) $zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
        }

        return $entries;
    }
}
