<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Unit\Export;

use Phil\BenefitDeliverables\Exception\ExportRefused;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use Phil\BenefitDeliverables\Export\LocalExportFileStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(LocalExportFileStore::class)]
#[CoversClass(ExportRefused::class)]
final class LocalExportFileStoreTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-benefit-deliverables-'.bin2hex(random_bytes(4));
        mkdir($this->directory, 0o700);
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');

        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testAFileIsWrittenReadAndErased(): void
    {
        $store = new LocalExportFileStore($this->directory.'/');
        $store->write(self::ID, static function (mixed $stream): void {
            fwrite($stream, "name,city\n");
        });

        self::assertSame([$this->directory.'/'.self::ID.'.csv'], glob($this->directory.'/*'));
        self::assertSame(0o600, fileperms($this->directory.'/'.self::ID.'.csv') & 0o777);
        $stream = $store->open(self::ID);
        self::assertIsResource($stream);
        self::assertSame("name,city\n", stream_get_contents($stream));
        fclose($stream);

        $store->delete(self::ID);
        $store->delete(self::ID);
        self::assertNull($store->open(self::ID));
        self::assertSame([], glob($this->directory.'/*'));
    }

    public function testAFailedWriteLeavesNoFile(): void
    {
        $store = new LocalExportFileStore($this->directory);

        try {
            $store->write(self::ID, static function (mixed $stream): never {
                fwrite($stream, 'half');

                throw new RuntimeException('The writer failed.');
            });
            self::fail('The write succeeded.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The writer failed.', $runtimeException->getMessage());
        }

        self::assertSame([], glob($this->directory.'/*'));
        self::assertNull($store->open(self::ID));
    }

    public function testAMissingDirectoryIsRefused(): void
    {
        $this->expectException(ExportRefused::class);
        $this->expectExceptionMessageIsOrContains('is not a writable directory.');

        new LocalExportFileStore($this->directory.'/missing')->write(self::ID, static function (): void {});
    }

    public function testOnlyAnExportIsNamed(): void
    {
        $store = new LocalExportFileStore($this->directory);

        foreach ([static fn (): mixed => $store->open('../secret'), static fn () => $store->delete('x'), static fn () => $store->write('X'.substr(self::ID, 1), static function (): void {})] as $call) {
            try {
                $call();
                self::fail('A name that is not an export was taken.');
            } catch (InvalidDeliverable $invalidDeliverable) {
                self::assertSame('An export is identified by 32 lower-case hexadecimal digits.', $invalidDeliverable->getMessage());
            }
        }
    }
}
