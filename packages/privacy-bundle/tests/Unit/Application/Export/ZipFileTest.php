<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Export;

use DateTimeImmutable;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Export\PlainExportReadme;
use Phil\PrivacyBundle\Application\Export\ZipFile;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Tests\Unit\Fixture\Unzip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use ZipArchive;

#[CoversClass(ZipFile::class)]
#[CoversClass(ExportPackage::class)]
#[CoversClass(PlainExportReadme::class)]
final class ZipFileTest extends TestCase
{
    public function testTheArchiveOpensWithTheZipExtensionWithEachFileAsWritten(): void
    {
        $zip = new ZipFile(new DateTimeImmutable('2026-11-10 14:37:58'));
        $zip->add('README.txt', 'Hello');
        $zip->add('données/membres.json', str_repeat('{"a": "répété"}', 200));
        $zip->add('empty.json', '');

        self::assertSame([
            'README.txt' => 'Hello',
            'données/membres.json' => str_repeat('{"a": "répété"}', 200),
            'empty.json' => '',
        ], Unzip::files($zip->bytes()));
    }

    public function testAFileThatCompressesIsDeflatedAndOneThatDoesNotIsStored(): void
    {
        $zip = new ZipFile(new DateTimeImmutable('2026-11-10 14:37:58'));
        $zip->add('a.txt', str_repeat('a', 1000));
        $zip->add('b.bin', random_bytes(64));

        $bytes = $zip->bytes();

        $path = (string) tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, $bytes);
        $archive = new ZipArchive();
        self::assertTrue($archive->open($path));
        $first = $archive->statIndex(0);
        $second = $archive->statIndex(1);
        self::assertIsArray($first);
        self::assertIsArray($second);
        self::assertSame(ZipArchive::CM_DEFLATE, $first['comp_method']);
        self::assertLessThan(1000, $first['comp_size']);
        self::assertSame(ZipArchive::CM_STORE, $second['comp_method']);
        self::assertSame(64, $second['comp_size']);
        $archive->close();
        unlink($path);
    }

    public function testTheHeadersAreThoseOfTheApplicationNote(): void
    {
        $zip = new ZipFile(new DateTimeImmutable('1980-01-01 00:00:00'));
        $zip->add('a', '');

        $bytes = $zip->bytes();

        self::assertSame("PK\x03\x04", substr($bytes, 0, 4));
        // Version 2.0, UTF-8 names, stored, 00:00:00, 1980-01-01, the CRC of nothing, no size, a name of one byte.
        self::assertSame(pack('vvvvvVVVvv', 20, 0x0800, 0, 0, 0x21, 0, 0, 0, 1, 0).'a', substr($bytes, 4, 27));
        self::assertSame("PK\x01\x02", substr($bytes, 31, 4));
        self::assertSame(pack('VvvvvVVv', 0x06054B50, 0, 0, 1, 1, 47, 31, 0), substr($bytes, -22));
        self::assertSame(pack('VvvvvVVv', 0x06054B50, 0, 0, 0, 0, 0, 0, 0), new ZipFile(new DateTimeImmutable('2000-01-01'))->bytes());

        // 14:37:58 is 14 << 11 | 37 << 5 | 58 / 2; 2026-11-10 is (2026 - 1980) << 9 | 11 << 5 | 10.
        $dated = new ZipFile(new DateTimeImmutable('2026-11-10 14:37:58'));
        $dated->add('a', '');
        self::assertSame(pack('vv', 29885, 23914), substr($dated->bytes(), 10, 4));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeNames(): iterable
    {
        yield 'empty' => [''];
        yield 'absolute' => ['/etc/passwd'];
        yield 'parent' => ['../a'];
        yield 'parent inside' => ['a/../../b'];
        yield 'current' => ['./a'];
        yield 'backslash' => ['a\\b'];
        yield 'directory' => ['a/'];
        yield 'control' => ["a\nb"];
        yield 'not UTF-8' => ["a\xB1"];
        yield 'too long' => [str_repeat('a', 256)];
    }

    #[DataProvider('unsafeNames')]
    public function testANameThatCouldEscapeIsRefused(string $name): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('is not a file name an archive can hold safely');

        new ZipFile(new DateTimeImmutable())->add($name, 'x');
    }

    public function testTheLongestNameAndTwoDotsInsideANameAreKept(): void
    {
        $zip = new ZipFile(new DateTimeImmutable('2026-01-01'));
        $zip->add(str_repeat('a', 255), 'x');
        $zip->add('a..b.json', 'y');

        self::assertSame([str_repeat('a', 255), 'a..b.json'], array_keys(Unzip::files($zip->bytes())));
    }

    public function testAFileNamedTwiceOrADateBefore1980IsRefused(): void
    {
        $zip = new ZipFile(new DateTimeImmutable('2026-01-01'));
        $zip->add('a', 'x');

        try {
            $zip->add('a', 'y');
            self::fail('A second file of the same name must be refused.');
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            self::assertSame('The archive already holds a file named "a".', $invalidPrivacyValue->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('A ZIP archive cannot date a file before 1980.');
        new ZipFile(new DateTimeImmutable('1979-12-31 23:59:59'));
    }

    public function testThePackageIsAReadmeThenOneJsonPerModule(): void
    {
        $package = new ExportPackage(new PlainExportReadme(), new MockClock('2026-11-10T14:00:00+02:00'));
        $files = Unzip::files($package->zip(['comments' => [], 'members' => ['name' => 'Éloïse', 'score' => 1.0, 'url' => 'https://example.org/a'], 'tickets' => ['ids' => [1]]]));

        self::assertSame(['README.txt', 'comments.json', 'members.json', 'tickets.json'], array_keys($files));
        self::assertSame("{}\n", $files['comments.json']);
        self::assertSame("{\n    \"name\": \"Éloïse\",\n    \"score\": 1.0,\n    \"url\": \"https://example.org/a\"\n}\n", $files['members.json']);
        self::assertSame(implode("\n", [
            'Your personal data',
            '==================',
            '',
            'This archive holds everything this site keeps about you, as of 2026-11-10 12:00 UTC.',
            'Each part of the site has its own file, in JSON: a text format that any text editor opens, and that other',
            'services can import.',
            '',
            'Files:',
            '',
            '- comments.json: nothing is kept about you here.',
            '- members.json: 3 entries.',
            '- tickets.json: 1 entry.',
            '',
            'Keep this archive somewhere safe: it is about you, and nobody else should read it.',
        ])."\n", $files['README.txt']);
    }
}
