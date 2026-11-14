<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Filesystem;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Filesystem\EncryptedExportArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(EncryptedExportArchive::class)]
final class EncryptedExportArchiveTest extends TestCase
{
    private const string A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-privacy-archive-'.bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testAnExportComesBackAndIsNeverInClear(): void
    {
        $archive = $this->archive();
        $archive->put(self::A, '{"name":"Éloïse"}', new DateTimeImmutable('2026-02-01T00:00:00Z'));

        self::assertSame('{"name":"Éloïse"}', $archive->get(self::A));
        $files = glob($this->directory.'/*');
        self::assertIsArray($files);
        self::assertSame([$this->directory.'/'.self::A.'.1769904000.export'], $files);
        self::assertStringNotContainsString('name', (string) file_get_contents($files[0]));
    }

    public function testTheFileAndItsDirectoryArePrivate(): void
    {
        $this->archive()->put(self::A, 'x', new DateTimeImmutable('2026-02-01T00:00:00Z'));

        self::assertSame(0o700, $this->permissions($this->directory));
        self::assertSame(0o600, $this->permissions($this->directory.'/'.self::A.'.1769904000.export'));
    }

    public function testAnotherSecretOpensNothing(): void
    {
        $this->archive()->put(self::A, 'x', new DateTimeImmutable('2026-02-01T00:00:00Z'));

        self::assertNull(new EncryptedExportArchive($this->directory, 'other')->get(self::A));
    }

    public function testAnAbsentExportIsNull(): void
    {
        new Filesystem()->mkdir($this->directory);

        self::assertNull($this->archive()->get(self::A));
    }

    public function testATruncatedFileIsNull(): void
    {
        $archive = $this->archive();
        $archive->put(self::A, 'x', new DateTimeImmutable('2026-02-01T00:00:00Z'));
        file_put_contents($this->directory.'/'.self::A.'.1769904000.export', str_repeat('a', \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + \SODIUM_CRYPTO_SECRETBOX_MACBYTES - 1));

        self::assertNull($archive->get(self::A));
    }

    public function testAFileShorterThanItsNonceIsNull(): void
    {
        $archive = $this->archive();
        $archive->put(self::A, 'x', new DateTimeImmutable('2026-02-01T00:00:00Z'));
        file_put_contents($this->directory.'/'.self::A.'.1769904000.export', str_repeat('a', \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES - 4));

        self::assertNull($archive->get(self::A));
    }

    public function testAPurgeLeavesWhatIsNotAnExportAndRemovesAnExportWithoutAnExpiry(): void
    {
        $archive = $this->archive();
        $archive->put(self::A, 'a', new DateTimeImmutable('2026-03-01T00:00:00Z'));
        file_put_contents($this->directory.'/notes.txt', 'kept');
        file_put_contents($this->directory.'/'.self::B.'.never.export', 'b');

        self::assertSame(1, $archive->purgeExpired(new DateTimeImmutable('2026-02-01T00:00:00Z')));
        self::assertFileExists($this->directory.'/notes.txt');
        self::assertFileDoesNotExist($this->directory.'/'.self::B.'.never.export');
        self::assertSame('a', $archive->get(self::A));
    }

    public function testAnExportOfADirectoryThatDoesNotExistIsAbsent(): void
    {
        self::assertNull($this->archive()->get(self::A));
        self::assertSame(0, $this->archive()->purgeExpired(new DateTimeImmutable('2026-02-01T00:00:00Z')));
    }

    public function testAFileOfExactlyTheOverheadIsOpenedAndRefused(): void
    {
        $archive = $this->archive();
        $archive->put(self::A, '', new DateTimeImmutable('2026-02-01T00:00:00Z'));

        self::assertSame('', $archive->get(self::A));
    }

    public function testAReferenceHasAShape(): void
    {
        $archive = $this->archive();

        foreach ([static fn (): ?string => $archive->get('../x'), fn () => $archive->put("{$this->directory}", 'x', new DateTimeImmutable())] as $call) {
            try {
                $call();
                self::fail('A malformed reference must not pass.');
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertStringContainsString('export reference', $invalidPrivacyValue->getMessage());
            }
        }
    }

    public function testAPurgeRemovesWhatHasExpiredAtTheInstantOnly(): void
    {
        $archive = $this->archive();
        $archive->put(self::A, 'a', new DateTimeImmutable('2026-02-01T00:00:00Z'));
        $archive->put(self::B, 'b', new DateTimeImmutable('2026-02-01T00:00:01Z'));

        self::assertSame(1, $archive->purgeExpired(new DateTimeImmutable('2026-02-01T00:00:00Z')));
        self::assertNull($archive->get(self::A));
        self::assertSame('b', $archive->get(self::B));
        self::assertSame(0, $archive->purgeExpired(new DateTimeImmutable('2026-02-01T00:00:00Z')));
    }

    private function archive(): EncryptedExportArchive
    {
        return new EncryptedExportArchive($this->directory, 'secret');
    }

    private function permissions(string $path): int
    {
        clearstatcache(true, $path);
        $permissions = fileperms($path);
        self::assertIsInt($permissions);

        return $permissions & 0o777;
    }
}
