<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\QuarantinedFile;
use Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(FilesystemQuarantine::class)]
#[CoversClass(QuarantinedFile::class)]
final class QuarantineInventoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = Temporary::directory();
    }

    protected function tearDown(): void
    {
        Temporary::remove($this->root);
    }

    public function testTheFilesAreListedTheMostRecentFirstAPageAtATime(): void
    {
        $quarantine = new FilesystemQuarantine($this->root, $this->clock('2026-11-12T08:00:00+00:00'));
        $first = $quarantine->isolate($this->stream('first file'), 'first.pdf', ScanResult::infected('Eicar-Test-Signature'));
        $second = $quarantine->isolate($this->stream('2nd'), 'second.png', ScanResult::infected('Win.Trojan'));
        $third = $quarantine->isolate($this->stream(''), 'third.txt', ScanResult::infected('Other'));
        touch($this->root.'/'.$first.'.json', 1_000);
        touch($this->root.'/'.$second.'.json', 3_000);
        touch($this->root.'/'.$third.'.json', 2_000);
        file_put_contents($this->root.'/notes.json', '{}');
        file_put_contents($this->root.'/'.str_repeat('A', 32).'.json', '{}');

        self::assertSame([$second, $third, $first], $this->ids($quarantine->files(10)));
        self::assertSame([$second, $third], $this->ids($quarantine->files(2)));
        self::assertSame([$third, $first], $this->ids($quarantine->files(2, 1)));
        self::assertSame([$first], $this->ids($quarantine->files(5, 2)));
        self::assertSame([], $quarantine->files(5, 3));

        $file = $quarantine->file($first);
        self::assertInstanceOf(QuarantinedFile::class, $file);
        self::assertSame([$first, 'first.pdf', 'Eicar-Test-Signature', '2026-11-12T08:00:00+00:00', 10], [$file->id, $file->name, $file->signature, $file->quarantinedAt->format(\DATE_ATOM), $file->size]);
        self::assertSame(0, $quarantine->file($third)?->size);
    }

    public function testFilesKeptInTheSameSecondAreListedByIdentifier(): void
    {
        $quarantine = new FilesystemQuarantine($this->root);
        $ids = [];

        foreach (['a', 'b', 'c'] as $name) {
            $id = $quarantine->isolate($this->stream($name), $name, ScanResult::infected('X'));
            touch($this->root.'/'.$id.'.json', 5_000);
            $ids[] = $id;
        }

        sort($ids);
        self::assertSame($ids, $this->ids($quarantine->files(3)));
    }

    public function testARecordThatCannotBeReadIsStillListedSoThatItCanBeDeleted(): void
    {
        $id = str_repeat('ab', 16);
        file_put_contents($this->root.'/'.$id.'.json', 'not json');
        touch($this->root.'/'.$id.'.json', 1_800_000_000);
        $other = str_repeat('cd', 16);
        file_put_contents($this->root.'/'.$other.'.json', '{"name": 7, "signature": ["x"], "quarantined_at": "yesterday"}');
        file_put_contents($this->root.'/'.$other.'.quarantine', 'xyz');
        touch($this->root.'/'.$other.'.json', 1_700_000_000);

        // An entry named as a record that is no file is not one.
        mkdir($this->root.'/'.str_repeat('ef', 16).'.json');
        touch($this->root.'/'.str_repeat('ef', 16).'.json', 1_900_000_000);

        $files = new FilesystemQuarantine($this->root)->files(10);
        self::assertCount(2, $files);
        self::assertNull(new FilesystemQuarantine($this->root)->file(str_repeat('ef', 16)));

        // A record that cannot be read is listed the same way.
        chmod($this->root.'/'.$other.'.json', 0o000);

        if (!is_readable($this->root.'/'.$other.'.json')) {
            $locked = new FilesystemQuarantine($this->root)->file($other);
            self::assertSame(['', null, '2023-11-14T22:13:20+00:00', 3], [$locked?->name, $locked?->signature, $locked?->quarantinedAt->format(\DATE_ATOM), $locked?->size]);
        }

        chmod($this->root.'/'.$other.'.json', 0o600);
        self::assertSame([$id, '', null, '2027-01-15T08:00:00+00:00', 0], [$files[0]->id, $files[0]->name, $files[0]->signature, $files[0]->quarantinedAt->format(\DATE_ATOM), $files[0]->size]);
        self::assertSame([$other, '', null, '2023-11-14T22:13:20+00:00', 3], [$files[1]->id, $files[1]->name, $files[1]->signature, $files[1]->quarantinedAt->format(\DATE_ATOM), $files[1]->size]);
    }

    public function testOnlyAnIdentifierOfTheQuarantineNamesAFile(): void
    {
        $quarantine = new FilesystemQuarantine($this->root);
        $id = $quarantine->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
        file_put_contents(\dirname($this->root).'/outside.json', '{"name": "outside"}');

        foreach (['', '../outside', strtoupper($id), $id.'0', substr($id, 1), $id."\n", str_repeat('0', 32)] as $wrong) {
            self::assertNull($quarantine->file($wrong), $wrong);
            self::assertFalse($quarantine->delete($wrong), $wrong);
        }

        self::assertCount(1, $quarantine->files(10));
        Temporary::remove(\dirname($this->root).'/outside.json');
    }

    public function testAFileIsDeletedWithItsRecord(): void
    {
        $quarantine = new FilesystemQuarantine($this->root);
        $id = $quarantine->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
        $kept = $quarantine->isolate($this->stream('y'), 'y', ScanResult::infected('Y'));

        self::assertTrue($quarantine->delete($id));
        self::assertFileDoesNotExist($this->root.'/'.$id.'.quarantine');
        self::assertFileDoesNotExist($this->root.'/'.$id.'.json');
        self::assertNull($quarantine->file($id));
        self::assertFalse($quarantine->delete($id));
        self::assertSame([$kept], $this->ids($quarantine->files(10)));

        // A record without its file, or a file without its record, is deleted all the same.
        unlink($this->root.'/'.$kept.'.quarantine');
        self::assertTrue($quarantine->delete($kept));
        $orphan = str_repeat('ef', 16);
        file_put_contents($this->root.'/'.$orphan.'.quarantine', 'z');
        self::assertTrue($quarantine->delete($orphan));
        self::assertSame(['.', '..'], scandir($this->root));
    }

    public function testAQuarantineNotYetCreatedHoldsNothing(): void
    {
        $quarantine = new FilesystemQuarantine($this->root.'/missing');

        self::assertSame([], $quarantine->files(10));
        self::assertNull($quarantine->file(str_repeat('a', 32)));
        self::assertFalse($quarantine->delete(str_repeat('a', 32)));
    }

    /**
     * @param list<QuarantinedFile> $files
     *
     * @return list<string>
     */
    private function ids(array $files): array
    {
        return array_map(static fn (QuarantinedFile $file): string => $file->id, $files);
    }

    private function clock(string $now): ClockInterface
    {
        return new readonly class(new DateTimeImmutable($now)) implements ClockInterface {
            public function __construct(private DateTimeImmutable $now)
            {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    /**
     * @return resource
     */
    private function stream(string $contents): mixed
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
