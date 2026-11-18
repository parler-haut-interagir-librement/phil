<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ReportingBundle\Application\Contract\ExportStore;
use Phil\ReportingBundle\Application\Contract\JobStore;
use Phil\ReportingBundle\Application\Exception\ExportFailed;
use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryExportStore;
use Phil\ReportingBundle\Infrastructure\Memory\InMemoryJobStore;
use Phil\ReportingBundle\Infrastructure\Store\DirectoryExportStore;
use Phil\ReportingBundle\Infrastructure\Store\DirectoryJobStore;
use Phil\ReportingBundle\Infrastructure\Store\PrivateDirectory;
use Phil\ReportingBundle\Tests\Unit\Fixture\FailingStream;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use Phil\ReportingBundle\Tests\Unit\Fixture\WatchedStream;
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
#[CoversClass(ExportFailed::class)]
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
        if (is_dir($this->directory)) {
            chmod($this->directory, 0o700);
        }

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

    public function testAFileIsPrivateWhileItIsWrittenAndAppearsWhole(): void
    {
        $exporting = [];
        $export = new DirectoryExportStore($this->directory)->keep(ReportStream::of(WatchedStream::open(function () use (&$exporting): void {
            foreach ($this->entries() as $entry) {
                $exporting[$entry] = fileperms($this->directory.'/'.$entry) & 0o777;
            }
        })), 'a.csv', 'text/csv', 1);

        self::assertCount(1, $exporting, 'Only the temporary file exists while the export is copied.');
        $temporary = array_key_first($exporting);
        self::assertIsString($temporary);
        self::assertMatchesRegularExpression('/^\.phil-/', $temporary);
        self::assertSame([$export->id.'.export'], $this->entries());
        self::assertSame(13, $export->size);

        $directory = new PrivateDirectory($this->directory.'/jobs', '.json');
        $entries = [];
        $mode = 0;
        [$id, $size] = $directory->keep(ReportStream::of(WatchedStream::open(function () use (&$entries, &$mode): void {
            $entries = $this->entries('/jobs');
            $mode = fileperms($this->directory.'/jobs/'.($entries[0] ?? '')) & 0o777;
        })), static fn (string $file): RuntimeException => new RuntimeException($file));

        self::assertSame(13, $size);
        self::assertCount(1, $entries, 'The file has no name while it is written.');
        self::assertMatchesRegularExpression('/^\.phil-/', implode('', $entries));
        self::assertSame([$id.'.json'], $this->entries('/jobs'));
        self::assertSame('watched bytes', file_get_contents($directory->file($id)));
        self::assertSame([$directory->keep('a job', static fn (string $file): RuntimeException => new RuntimeException($file))[1]], [5]);

        if ('Windows' !== \PHP_OS_FAMILY) {
            self::assertSame([0o600], array_values($exporting));
            self::assertSame(0o600, $mode);
            self::assertSame(0o600, fileperms($directory->file($id)) & 0o777);
            self::assertSame(0o600, fileperms($this->directory.'/'.$export->id.'.export') & 0o777);
        }
    }

    public function testTwoFilesKeptAtTheSameMomentNeverMeet(): void
    {
        $directory = new PrivateDirectory($this->directory, '.json');
        $failure = static fn (string $file): RuntimeException => new RuntimeException($file);
        $inner = null;

        [$outer] = $directory->keep(ReportStream::of(WatchedStream::open(static function () use ($directory, $failure, &$inner): void {
            [$inner] = $directory->keep('inner', $failure);
        })), $failure);

        self::assertIsString($inner);
        self::assertSame('watched bytes', file_get_contents($directory->file($outer)));
        self::assertSame('inner', file_get_contents($directory->file($inner)));
        self::assertCount(2, $this->entries());
    }

    public function testAStreamThatFailsLeavesNothing(): void
    {
        $directory = new PrivateDirectory($this->directory, '.json');

        try {
            $directory->keep(ReportStream::of(WatchedStream::open(static function (): never {
                throw new RuntimeException('The source broke.');
            })), static fn (string $file): RuntimeException => new RuntimeException($file));
            self::fail('Kept.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The source broke.', $runtimeException->getMessage());
        }

        self::assertSame([], $this->entries());
    }

    public function testAnOpenDirectoryIsNarrowedBeforeAFileIsKept(): void
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            self::markTestSkipped("Windows has no group or other users' bits.");
        }

        mkdir($this->directory, 0o700);

        foreach ([0o755, 0o750, 0o705, 0o710, 0o701] as $mode) {
            foreach ([static fn (string $d): mixed => new DirectoryJobStore($d)->keep(new ReportJob('sales', 'csv', Reporting::request())), static fn (string $d): mixed => new DirectoryExportStore($d)->keep(self::stream('x'), 'a', 'b', 0)] as $call) {
                chmod($this->directory, $mode);
                $call($this->directory);
                clearstatcache();

                self::assertSame(0o700, fileperms($this->directory) & 0o777);
            }
        }
    }

    public function testAnOpenDirectoryIsRefusedWhenItMayNotOrCannotBeNarrowed(): void
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            self::markTestSkipped("Windows has no group or other users' bits.");
        }

        mkdir($this->directory, 0o751);
        chmod($this->directory, 0o751);

        foreach ([static fn (string $d): mixed => new DirectoryJobStore($d, false)->keep(new ReportJob('sales', 'csv', Reporting::request())), static fn (string $d): mixed => new DirectoryExportStore($d, false)->keep(self::stream('x'), 'a', 'b', 0)] as $call) {
            try {
                $call($this->directory);
                self::fail('The directory is open.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame(\sprintf('The directory "%s" is open to other users (mode 0751): narrow it to its owner, with mode 0700.', $this->directory), $runtimeException->getMessage());
            }
        }

        clearstatcache();
        self::assertSame(0o751, fileperms($this->directory) & 0o777);
        self::assertSame([], $this->entries());
        chmod($this->directory, 0o700);
        $id = new DirectoryJobStore($this->directory, false)->keep(new ReportJob('sales', 'csv', Reporting::request()));
        self::assertSame([$id.'.json'], $this->entries(), 'A private directory is used as it is.');
        $fresh = new DirectoryExportStore($this->directory.'/fresh/exports', false)->keep(self::stream('x'), 'a', 'b', 0);
        self::assertSame([$fresh->id.'.export'], $this->entries('/fresh/exports'), 'A directory it creates is private.');

        // A directory the tests created belongs to the user they run as.
        if (0 === fileowner($this->directory)) {
            self::markTestSkipped('root narrows every directory.');
        }

        // The system's shared temporary directory belongs to root and is open to everyone.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The directory "/tmp" is open to other users (mode 0777) and cannot be narrowed to its owner: give it to the user that runs the application, with mode 0700.');
        new DirectoryJobStore('/tmp')->keep(new ReportJob('sales', 'csv', Reporting::request()));
    }

    public function testADirectoryReachedThroughASymbolicLinkKeepsTheFiles(): void
    {
        mkdir($this->directory.'/real', 0o700, true);
        symlink($this->directory.'/real', $this->directory.'/link');
        $id = new DirectoryJobStore($this->directory.'/link')->keep(new ReportJob('sales', 'csv', Reporting::request()));

        self::assertFileExists($this->directory.'/real/'.$id.'.json');
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

    public function testAnExportThatCannotBeCreatedFails(): void
    {
        $store = new DirectoryExportStore($this->directory);
        $store->keep(self::stream('x'), 'a.csv', 'text/csv', 1);
        chmod($this->directory, 0o500);
        clearstatcache();

        if (is_writable($this->directory)) {
            self::markTestSkipped('The superuser writes in a directory without the right to.');
        }

        $fallback = (string) realpath(sys_get_temp_dir());
        $before = glob($fallback.'/.phil-*');

        try {
            $store->keep(self::stream('y'), 'b.csv', 'text/csv', 1);
            self::fail('Kept.');
        } catch (ExportFailed $exportFailed) {
            self::assertMatchesRegularExpression('#^The export failed: "'.preg_quote($this->directory, '#').'/[0-9a-f]{32}\.export" cannot be created\.$#D', $exportFailed->getMessage());
        }

        self::assertSame($before, glob($fallback.'/.phil-*'), 'The file tempnam() falls back to elsewhere is removed.');
    }

    public function testAnExportCutShortLeavesNoFile(): void
    {
        FailingStream::register();
        $store = new DirectoryExportStore($this->directory);
        $store->keep(self::stream('x'), 'a.csv', 'text/csv', 1);

        try {
            $store->keep(ReportStream::of(fopen(FailingStream::SCHEME.'://read', 'r')), 'b.csv', 'text/csv', 1);
            self::fail('Kept.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The source broke.', $runtimeException->getMessage());
        }

        self::assertCount(1, $this->entries(), 'Neither the file cut short nor its temporary file is left.');
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

    /**
     * @return list<string>
     */
    private function entries(string $in = ''): array
    {
        $entries = scandir($this->directory.$in);

        return array_values(array_diff(false === $entries ? [] : $entries, ['.', '..']));
    }

    private static function stream(string $content): ReportStream
    {
        $stream = ReportStream::temporary();
        $stream->write($content);
        $stream->rewind();

        return $stream;
    }
}
