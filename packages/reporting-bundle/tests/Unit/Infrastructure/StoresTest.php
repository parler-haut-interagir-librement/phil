<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ReportingBundle\Application\Contract\ExportStore;
use Phil\ReportingBundle\Application\Contract\JobStore;
use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryExportStore;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryJobStore;
use Phil\ReportingBundle\Infrastructure\Store\DirectoryExportStore;
use Phil\ReportingBundle\Infrastructure\Store\DirectoryJobStore;
use Phil\ReportingBundle\Infrastructure\Store\PrivateDirectory;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(DirectoryJobStore::class)]
#[CoversClass(DirectoryExportStore::class)]
#[CoversClass(PrivateDirectory::class)]
#[CoversClass(InMemoryJobStore::class)]
#[CoversClass(InMemoryExportStore::class)]
#[CoversClass(JobUnavailable::class)]
final class StoresTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-reporting-'.sodium_bin2hex(random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testTheJobStores(): void
    {
        foreach ([new DirectoryJobStore($this->directory.'/jobs'), new InMemoryJobStore()] as $store) {
            $this->jobs($store);
        }

        self::assertSame(0o700, fileperms($this->directory.'/jobs') & 0o777);
    }

    public function testTheExportStores(): void
    {
        foreach ([new DirectoryExportStore($this->directory.'/exports'), new InMemoryExportStore()] as $store) {
            $this->exports($store);
        }
    }

    public function testADirectoryFileIsPrivateAndRefusedWhenItIsNotAJob(): void
    {
        $store = new DirectoryJobStore($this->directory);
        $id = $store->keep(new ReportJob('sales', 'csv', Reporting::request()));
        $file = $this->directory.'/'.$id.'.json';

        self::assertSame(0o600, fileperms($file) & 0o777);
        self::assertStringStartsWith('{"report":"sales","format":"csv","request":{"start":"2026-10-01T00:00:00-04:00"', (string) file_get_contents($file));
        $raw = (string) file_get_contents($this->directory.'/'.$store->keep(new ReportJob('sales', 'csv', Reporting::request(['path' => 'a/é', 'ratio' => 1.0]))).'.json');
        self::assertStringContainsString('"parameters":{"path":"a/é","ratio":1.0}', $raw);

        foreach (['{"report":"sales"}', 'not json', '"deep"', str_repeat('[', 20).str_repeat(']', 20)] as $content) {
            file_put_contents($file, $content);

            try {
                $store->find($id);
                self::fail($content);
            } catch (JobUnavailable $jobUnavailable) {
                self::assertSame(\sprintf('The report job or export "%s" is unavailable: its file is not a job of the store.', $id), $jobUnavailable->getMessage());
            }
        }

        $exports = new DirectoryExportStore($this->directory);
        $export = $exports->keep(self::stream('x'), 'a.csv', 'text/csv', 1);
        self::assertSame(0o600, fileperms($this->directory.'/'.$export->id.'.export') & 0o777);
        self::assertFileExists($file);
    }

    public function testAnIdentifierOutOfShapeNeverReachesTheDisk(): void
    {
        foreach (['../../etc/passwd', 'x'.str_repeat('0', 32), str_repeat('A', 32), str_repeat('0', 31), str_repeat('0', 32)."\n"] as $id) {
            $calls = [
                static function (DirectoryJobStore $jobs, DirectoryExportStore $exports) use ($id): void {
                    $jobs->find($id);
                },
                static function (DirectoryJobStore $jobs, DirectoryExportStore $exports) use ($id): void {
                    $exports->open($id);
                },
                static function (DirectoryJobStore $jobs, DirectoryExportStore $exports) use ($id): void {
                    $jobs->remove($id);
                },
            ];

            foreach ($calls as $call) {
                try {
                    $call(new DirectoryJobStore($this->directory), new DirectoryExportStore($this->directory));
                    self::fail($id);
                } catch (JobUnavailable $jobUnavailable) {
                    self::assertStringEndsWith('is unavailable: not an identifier of the store.', $jobUnavailable->getMessage());
                }
            }
        }
    }

    public function testADirectoryThatCannotBeCreated(): void
    {
        file_put_contents($this->directory, 'a file');

        foreach ([static fn (string $d): mixed => new DirectoryJobStore($d.'/jobs')->keep(new ReportJob('sales', 'csv', Reporting::request())), static fn (string $d): mixed => new DirectoryExportStore($d.'/exports')->keep(self::stream('x'), 'a', 'b', 0)] as $call) {
            try {
                $call($this->directory);
                self::fail('Created.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame(\sprintf('The directory "%s" cannot be created.', $this->directory.(str_contains($runtimeException->getMessage(), 'jobs') ? '/jobs' : '/exports')), $runtimeException->getMessage());
            }
        }
    }

    public function testTheMemoryStoresAreReset(): void
    {
        $jobs = new InMemoryJobStore();
        $id = $jobs->keep(new ReportJob('sales', 'csv', Reporting::request()));
        self::assertSame([$id], $jobs->ids());

        $exports = new InMemoryExportStore();
        $exports->keep(self::stream('x'), 'a', 'b', 0);

        $jobs->reset();
        $exports->reset();

        self::assertSame([[], []], [$jobs->ids(), $exports->contents()]);
    }

    private function jobs(JobStore $store): void
    {
        $job = new ReportJob('sales', 'csv', Reporting::request(['status' => 'paid']), 'mail', ['ada@example.com']);
        $id = $store->keep($job);
        $other = $store->keep($job);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id);
        self::assertNotSame($id, $other);
        self::assertEquals($job, $store->find($id));

        $store->remove($id);
        $store->remove($id);
        self::assertEquals($job, $store->find($other));

        try {
            $store->find($id);
            self::fail('Found.');
        } catch (JobUnavailable $jobUnavailable) {
            self::assertSame(\sprintf('The report job or export "%s" is unavailable: no job is kept under it.', $id), $jobUnavailable->getMessage());
        }
    }

    private function exports(ExportStore $store): void
    {
        $export = $store->keep(self::stream("a,b\r\n1,2\r\n"), 'sales.csv', 'text/csv', 1);
        $other = $store->keep(self::stream(''), 'empty.csv', 'text/csv', 0);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $export->id);
        self::assertNotSame($export->id, $other->id);
        self::assertSame(['sales.csv', 'text/csv', 10, 1], [$export->filename, $export->mediaType, $export->size, $export->rows]);
        self::assertSame(0, $other->size);
        self::assertSame("a,b\r\n1,2\r\n", $store->open($export->id)->contents());
        self::assertSame('', $store->open($other->id)->contents());

        $store->remove($export->id);
        $store->remove($export->id);

        try {
            $store->open($export->id);
            self::fail('Opened.');
        } catch (JobUnavailable $jobUnavailable) {
            self::assertSame(\sprintf('The report job or export "%s" is unavailable: no export is kept under it.', $export->id), $jobUnavailable->getMessage());
        }
    }

    private static function stream(string $content): ReportStream
    {
        $stream = ReportStream::temporary();
        $stream->write($content);
        $stream->rewind();

        return $stream;
    }
}
