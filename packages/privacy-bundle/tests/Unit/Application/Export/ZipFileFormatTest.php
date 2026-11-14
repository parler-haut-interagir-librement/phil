<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Export;

use DateTimeImmutable;
use Phil\PrivacyBundle\Application\Export\ZipFile;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(ZipFile::class)]
final class ZipFileFormatTest extends TestCase
{
    public function testEachCentralDirectoryRecordIsThatOfTheApplicationNote(): void
    {
        $zip = new ZipFile(new DateTimeImmutable('1980-01-01 00:00:00'));
        $zip->add('a', '');
        $zip->add('b', '');

        $bytes = $zip->bytes();

        // Each local header is 30 bytes and a name of one byte; the directory follows the two of them.
        $record = static fn (string $name, int $offset): string => pack('Vv', 0x02014B50, 20)
            .pack('vvvvvVVVv', 20, 0x0800, 0, 0, 0x21, 0, 0, 0, 1)
            // No extra field, no comment, the first disk, no internal or external attribute, then the local header.
            .pack('vvvvVV', 0, 0, 0, 0, 0, $offset)
            .$name;

        self::assertSame($record('a', 0).$record('b', 31), substr($bytes, 62, 94));
    }

    #[RequiresPhpExtension('zlib')]
    public function testAFileThatDeflatingDoesNotShortenIsStored(): void
    {
        // Five bytes deflate to five bytes, six to five.
        self::assertSame(5, \strlen((string) gzdeflate('aaaaa', 6)));

        $zip = new ZipFile(new DateTimeImmutable('2026-01-01'));
        $zip->add('a', 'aaaaa');

        $bytes = $zip->bytes();

        self::assertSame(pack('v', 0), substr($bytes, 8, 2));
        self::assertSame(pack('VV', 5, 5), substr($bytes, 18, 8));
        self::assertSame('aaaaa', substr($bytes, 31, 5));

        $shorter = new ZipFile(new DateTimeImmutable('2026-01-01'));
        $shorter->add('a', 'aaaaaa');

        self::assertSame(pack('v', 8), substr($shorter->bytes(), 8, 2));
    }

    #[RequiresPhpExtension('zlib')]
    public function testAFileIsDeflatedAtTheDefaultLevelSoThatTheSameExportGivesTheSameBytes(): void
    {
        $content = (string) json_encode(array_map(static fn (int $i): array => ['id' => $i, 'name' => 'Member '.$i, 'email' => 'm'.$i.'@example.org'], range(1, 300)));
        $deflated = (string) gzdeflate($content, 6);

        $zip = new ZipFile(new DateTimeImmutable('2026-01-01'));
        $zip->add('a', $content);

        self::assertSame($deflated, substr($zip->bytes(), 31, \strlen($deflated)));
        self::assertNotSame($deflated, gzdeflate($content, 5));
        self::assertNotSame($deflated, gzdeflate($content, 7));
    }

    public function testTheArchiveHoldsAsManyFilesAsThePlainFormatCountsAndNoMore(): void
    {
        $zip = new ZipFile(new DateTimeImmutable('2026-01-01'));

        for ($i = 0; $i < ZipFile::MAX_FILES; ++$i) {
            $zip->add((string) $i, '');
        }

        self::assertSame(pack('vv', ZipFile::MAX_FILES, ZipFile::MAX_FILES), substr($zip->bytes(), -14, 4));

        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('A ZIP archive without ZIP64 holds at most 65535 files.');

        $zip->add('one more', '');
    }
}
