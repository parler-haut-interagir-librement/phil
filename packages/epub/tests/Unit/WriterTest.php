<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit;

use Phil\Epub\Chapter;
use Phil\Epub\EpubWriter;
use Phil\Epub\Exception\EpubException;
use Phil\Epub\Exception\WriteFailed;
use Phil\Epub\Internal\FontObfuscation;
use Phil\Epub\Tests\Unit\Fixture\Archive;
use Phil\Epub\Tests\Unit\Fixture\Books;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(EpubWriter::class)]
#[CoversClass(FontObfuscation::class)]
#[CoversClass(WriteFailed::class)]
final class WriterTest extends TestCase
{
    public function testTheMimetypeComesFirstStoredAndEveryEntryCarriesTheModificationTime(): void
    {
        $bytes = new EpubWriter()->toString(Books::book()->withChapter(new Chapter('<p>a</p>')));
        $archive = Archive::read($bytes);

        self::assertStringStartsWith("PK\x03\x04", $bytes);
        self::assertSame('mimetype', substr($bytes, 30, 8));
        self::assertSame('application/epub+zip', substr($bytes, 38, 20));
        self::assertSame("\0\0", substr($bytes, 8, 2));

        foreach ($archive->entries as $name => $entry) {
            self::assertSame('mimetype' === $name ? ZipArchive::CM_STORE : ZipArchive::CM_DEFLATE, $entry['method'], $name);
            self::assertEqualsWithDelta(Books::modified()->getTimestamp(), $entry['mtime'], 2.0, $name);
        }
    }

    public function testTheSameBookGivesTheSameBytes(): void
    {
        $book = Books::book()->withChapter(new Chapter('<p>a</p>'));

        self::assertSame(new EpubWriter()->toString($book), new EpubWriter()->toString($book));
    }

    public function testWriteToAFile(): void
    {
        $path = sys_get_temp_dir().'/phil-epub-'.bin2hex(random_bytes(4)).'.epub';
        $book = Books::book()->withChapter(new Chapter('<p>a</p>'));
        file_put_contents($path, 'old content');

        try {
            new EpubWriter()->write($book, $path);
            self::assertSame(new EpubWriter()->toString($book), file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testAnArchiveThatCannotBeCreated(): void
    {
        $path = sys_get_temp_dir().'/phil-epub-missing-'.bin2hex(random_bytes(4)).'/book.epub';

        try {
            new EpubWriter()->write(Books::book()->withChapter(new Chapter('<p>a</p>')), $path);
            self::fail('No exception.');
        } catch (WriteFailed $writeFailed) {
            self::assertInstanceOf(EpubException::class, $writeFailed);
            self::assertSame(\sprintf('The archive "%s" cannot be created.', $path), $writeFailed->getMessage());
        }
    }

    public function testAnArchiveThatCannotBeOpened(): void
    {
        $path = sys_get_temp_dir();

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The archive "%s" cannot be created.', $path));

        new EpubWriter()->write(Books::book()->withChapter(new Chapter('<p>a</p>')), $path);
    }

    public function testTheTemporaryFileIsWrittenInTheGivenDirectoryThenRemoved(): void
    {
        $directory = sys_get_temp_dir().'/phil-epub-tmp-'.bin2hex(random_bytes(4));
        mkdir($directory);
        touch($directory, time() - 1000);
        clearstatcache();
        $before = filemtime($directory);

        try {
            $bytes = new EpubWriter($directory)->toString(Books::book()->withChapter(new Chapter('<p>a</p>')));
            clearstatcache();

            self::assertStringStartsWith('PK', $bytes);
            self::assertSame(['.', '..'], scandir($directory));
            self::assertGreaterThan($before, filemtime($directory));
        } finally {
            $files = glob($directory.'/*');
            array_map(unlink(...), false === $files ? [] : $files);
            rmdir($directory);
        }
    }

    public function testFontObfuscation(): void
    {
        $font = random_bytes(2000);
        $identifier = " urn:uuid:\t7c9e6679-7425-40de-944b-e07fc1f90ae7\r\n";
        // SHA-1 of urn:uuid:7c9e6679-7425-40de-944b-e07fc1f90ae7
        $key = hex2bin('ef3942f1adf33635b686b77d5d735c55e0fc33fa');
        self::assertIsString($key);
        $obfuscated = FontObfuscation::apply($font, $identifier);

        self::assertSame(2000, \strlen($obfuscated));
        self::assertSame(1040, \strlen(FontObfuscation::apply(substr($font, 0, 1040), $identifier)));
        self::assertSame(1041, \strlen(FontObfuscation::apply(substr($font, 0, 1041), $identifier)));
        self::assertSame($font[0] ^ $key[0], $obfuscated[0]);
        self::assertSame($font[19] ^ $key[19], $obfuscated[19]);
        self::assertSame($font[20] ^ $key[0], $obfuscated[20]);
        self::assertSame($font[1039] ^ $key[19], $obfuscated[1039]);
        self::assertSame(substr($font, 1040), substr($obfuscated, 1040));
        self::assertSame($font, FontObfuscation::apply($obfuscated, $identifier));
        self::assertSame('ab' ^ substr($key, 0, 2), FontObfuscation::apply('ab', $identifier));
    }
}
