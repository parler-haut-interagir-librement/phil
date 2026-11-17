<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Unit;

use Phil\ElearningPackages\Archive\ZipPackage;
use Phil\ElearningPackages\Archive\ZipPackageOpener;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\Tests\Fixture\Packages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

#[CoversClass(ZipPackage::class)]
#[CoversClass(ZipPackageOpener::class)]
final class ZipPackageTest extends TestCase
{
    public function testAPackageIsListedAndReadWithinItsLimits(): void
    {
        $path = $this->build(static function (ZipArchive $zip): void {
            $zip->addEmptyDir('content');
            $zip->addFromString('content/a.html', 'Hello');
            $zip->addFromString('empty.txt', '');
            $zip->addFromString('é/b.html', str_repeat('b', 70_000));
        });
        $package = new ZipPackageOpener()->open($path, new PackageLimits());

        self::assertSame(['content/a.html', 'empty.txt', 'é/b.html'], $package->files());
        self::assertTrue($package->has('content/a.html'));
        self::assertFalse($package->has('content'));
        self::assertSame('Hello', $package->read('content/a.html'));
        self::assertSame('', $package->read('empty.txt'));
        self::assertSame(70_000, \strlen($package->read('é/b.html')));

        try {
            $package->read('missing.html');
            self::fail('The file is read.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The package has no file "missing.html".', $packageRefused->getMessage());
        }

        $package->close();
        $package->close();

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The file "content/a.html" of the package cannot be read.');

        $package->read('content/a.html');
    }

    public function testTheBytesReadAcrossThePackageAreCounted(): void
    {
        $package = ZipPackage::open(Packages::zip(['a.txt' => str_repeat('a', 60), 'b.txt' => str_repeat('b', 60)]), new PackageLimits(totalBytes: 120));

        self::assertSame(60, \strlen($package->read('a.txt')));
        self::assertSame(60, \strlen($package->read('b.txt')));

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The package uncompresses to more than 120 bytes.');

        $package->read('a.txt');
    }

    public function testAFileThatIsNotAnArchiveIsRefused(): void
    {
        $file = sys_get_temp_dir().'/phil-courses-not-a-zip-'.bin2hex(random_bytes(4)).'.zip';
        file_put_contents($file, 'not a zip');

        foreach ([$file => 'The package is not a ZIP archive.', $file.'.missing' => 'The package is not a readable file of 536870912 bytes at most.'] as $path => $message) {
            try {
                ZipPackage::open($path, new PackageLimits());
                self::fail('The file is accepted.');
            } catch (PackageRefused $packageRefused) {
                self::assertSame($message, $packageRefused->getMessage());
            }
        }

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The package is not a readable file of 8 bytes at most.');

        ZipPackage::open($file, new PackageLimits(archiveBytes: 8));
    }

    /**
     * @return iterable<string, array{callable(ZipArchive): mixed, PackageLimits, string}>
     */
    public static function unsafeArchives(): iterable
    {
        yield 'parent' => [static fn (ZipArchive $zip): bool => $zip->addFromString('../evil.php', 'x'), new PackageLimits(), 'holds an empty, "." or ".." segment'];
        yield 'nested parent' => [static fn (ZipArchive $zip): bool => $zip->addFromString('a/../../evil.php', 'x'), new PackageLimits(), 'holds an empty, "." or ".." segment'];
        yield 'absolute' => [static fn (ZipArchive $zip): bool => $zip->addFromString('/etc/cron.d/evil', 'x'), new PackageLimits(), 'is absolute'];
        yield 'backslash' => [static fn (ZipArchive $zip): bool => $zip->addFromString('..\\evil.php', 'x'), new PackageLimits(), 'holds a backslash'];
        yield 'directory parent' => [static fn (ZipArchive $zip): bool => $zip->addEmptyDir('../up'), new PackageLimits(), 'holds an empty, "." or ".." segment'];
        yield 'twice by case' => [static function (ZipArchive $zip): void {
            $zip->addFromString('Index.html', 'a');
            $zip->addFromString('index.HTML', 'b');
        }, new PackageLimits(), 'The package holds "Index.html" and "index.HTML", which differ only by the case of their letters.'];
        yield 'symbolic link' => [static function (ZipArchive $zip): void {
            $zip->addFromString('link', '/etc/passwd');
            $zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, 0o120777 << 16);
        }, new PackageLimits(), 'The entry "link" of the package is a symbolic link.'];
        yield 'too many entries' => [static function (ZipArchive $zip): void {
            $zip->addFromString('a', 'a');
            $zip->addFromString('b', 'b');
            $zip->addFromString('c', 'c');
        }, new PackageLimits(entries: 2), 'The package holds more than 2 entries.'];
        yield 'large entry' => [static fn (ZipArchive $zip): bool => $zip->addFromString('a', str_repeat('a', 11)), new PackageLimits(entryBytes: 10), 'The entry "a" of the package uncompresses to more than 10 bytes.'];
        yield 'large total' => [static function (ZipArchive $zip): void {
            $zip->addFromString('a', str_repeat('a', 6));
            $zip->addFromString('b', str_repeat('b', 5));
        }, new PackageLimits(totalBytes: 10), 'The package uncompresses to more than 10 bytes.'];
        yield 'bomb' => [static fn (ZipArchive $zip): bool => $zip->addFromString('bomb', str_repeat("\0", 2 * 1024 * 1024)), new PackageLimits(ratio: 100), 'is compressed more than 100 times: a compression bomb is refused'];
    }

    /**
     * @param callable(ZipArchive): mixed $fill
     */
    #[DataProvider('unsafeArchives')]
    public function testAnUnsafeArchiveIsRefused(callable $fill, PackageLimits $limits, string $message): void
    {
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        ZipPackage::open($this->build($fill), $limits);
    }

    public function testTheBoundsAreInclusive(): void
    {
        $package = ZipPackage::open($this->build(static function (ZipArchive $zip): void {
            $zip->addFromString('a', str_repeat('a', 5));
            $zip->addFromString('b', str_repeat('b', 5));
        }), new PackageLimits(entries: 2, entryBytes: 5, totalBytes: 10));
        self::assertCount(2, $package->files());

        $compressible = ZipPackage::open($this->build(static fn (ZipArchive $zip): bool => $zip->addFromString('small', str_repeat("\0", 1024 * 1024))), new PackageLimits(ratio: 1));
        self::assertSame(['small'], $compressible->files());

        $link = ZipPackage::open($this->build(static function (ZipArchive $zip): void {
            $zip->addFromString('file', 'x');
            $zip->setExternalAttributesName('file', ZipArchive::OPSYS_UNIX, 0o100644 << 16);
            $zip->addFromString('dos', 'x');
            $zip->setExternalAttributesName('dos', ZipArchive::OPSYS_DOS, 0o120777 << 16);
        }), new PackageLimits());
        self::assertSame(['file', 'dos'], $link->files());
    }

    public function testAnEntryWhoseHeaderLiesIsRefused(): void
    {
        $path = $this->build(static function (ZipArchive $zip): void {
            $zip->addFromString('a.txt', str_repeat('a', 100));
            $zip->setCompressionName('a.txt', ZipArchive::CM_STORE);
        });
        $bytes = (string) file_get_contents($path);
        $central = strpos($bytes, "PK\x01\x02");

        if (false === $central) {
            throw new RuntimeException('The archive has no central directory.');
        }

        // The uncompressed size of the central directory entry, at offset 24, says 10 bytes instead of 100.
        file_put_contents($path, substr_replace($bytes, pack('V', 10), $central + 24, 4));

        $this->expectException(PackageRefused::class);

        ZipPackage::open($path, new PackageLimits())->read('a.txt');
    }

    public function testAnEntryWhoseDataIsCorruptIsRefusedWithoutAWarning(): void
    {
        $path = $this->build(static function (ZipArchive $zip): void {
            $zip->addFromString('a.txt', str_repeat('a', 100));
            $zip->setCompressionName('a.txt', ZipArchive::CM_STORE);
        });
        $bytes = (string) file_get_contents($path);
        $data = strpos($bytes, str_repeat('a', 100));

        if (false === $data) {
            throw new RuntimeException('The archive does not store its data as is.');
        }

        file_put_contents($path, substr_replace($bytes, 'b', $data + 50, 1));
        $package = ZipPackage::open($path, new PackageLimits());

        try {
            $package->read('a.txt');
            self::fail('The corrupt file is read.');
        } catch (PackageRefused $packageRefused) {
            self::assertSame('The file "a.txt" of the package is corrupt: its checksum does not match.', $packageRefused->getMessage());
        }
    }

    public function testAnEntryWhoseDataEndsEarlyIsRefusedWithoutAWarning(): void
    {
        // An archive the fuzzer wrote: its manifest's deflated data is broken, which the zip stream reports as a warning,
        // in words that depend on the version of libzip.
        $package = ZipPackage::open(__DIR__.'/../Fixture/truncated.zip', new PackageLimits());

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The file "imsmanifest.xml" of the package is corrupt: fread(): Zip stream error: ');

        $package->read('imsmanifest.xml');
    }

    public function testAnEncryptedEntryIsRefused(): void
    {
        $path = $this->build(static function (ZipArchive $zip): void {
            $zip->addFromString('secret.txt', 'x');
            $zip->setEncryptionName('secret.txt', ZipArchive::EM_AES_256, 'a password');
        });

        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessageIsOrContains('The entry "secret.txt" of the package is encrypted.');

        ZipPackage::open($path, new PackageLimits());
    }

    /**
     * @param callable(ZipArchive): mixed $fill
     */
    private function build(callable $fill): string
    {
        $path = sys_get_temp_dir().'/phil-courses-zip-'.bin2hex(random_bytes(6)).'.zip';
        $zip = new ZipArchive();

        if (true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new RuntimeException('The archive cannot be written.');
        }

        $fill($zip);
        $zip->close();

        return $path;
    }
}
