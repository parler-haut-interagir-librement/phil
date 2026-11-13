<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Infrastructure;

use ArrayObject;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Override;
use Phil\StatusPageBundle\Application\Snapshot\Snapshot;
use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;
use Phil\StatusPageBundle\Infrastructure\Publisher\DirectoryPublisher;
use Phil\StatusPageBundle\Infrastructure\Publisher\FlysystemPublisher;
use Phil\StatusPageBundle\Infrastructure\Records\ConfiguredRecords;
use Phil\StatusPageBundle\Infrastructure\Records\JsonFileRecords;
use Phil\StatusPageBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;

#[CoversClass(ConfiguredRecords::class)]
#[CoversClass(JsonFileRecords::class)]
#[CoversClass(DirectoryPublisher::class)]
#[CoversClass(FlysystemPublisher::class)]
final class StoresTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-status-page-stores-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        new LocalFilesystem()->remove($this->directory);
    }

    public function testTheConfiguredRecordsAreReadOnce(): void
    {
        $records = new ConfiguredRecords($this->data());

        self::assertSame('slow', $records->incidents()[0]->id);
        self::assertSame('upgrade', $records->maintenances()[0]->id);
        self::assertSame($records->incidents(), $records->incidents());
    }

    public function testTheJsonFileIsReadOnEachPage(): void
    {
        $path = $this->directory.'/records.json';
        $records = new JsonFileRecords($path);

        self::assertSame([], $records->incidents());
        self::assertSame([], $records->maintenances());

        file_put_contents($path, json_encode($this->data(), \JSON_THROW_ON_ERROR));
        $written = new JsonFileRecords($path);
        self::assertSame('slow', $written->incidents()[0]->id);
        self::assertSame('upgrade', $written->maintenances()[0]->id);

        file_put_contents($path, '{"incidents": []}');
        self::assertSame([], $written->incidents());
    }

    public function testAFileOfTheLargestSizeIsRead(): void
    {
        $path = $this->directory.'/records.json';
        file_put_contents($path, '{}'.str_repeat(' ', JsonFileRecords::MAX_BYTES - 2));

        self::assertSame([], new JsonFileRecords($path)->incidents());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedFiles(): iterable
    {
        yield 'too large' => ['{}'.str_repeat(' ', JsonFileRecords::MAX_BYTES - 1), 'The records file is readable and 1048576 bytes at most.'];
        yield 'not JSON' => ['{incidents', 'The records file is JSON.'];
        yield 'too deep' => [str_repeat('[', 16).str_repeat(']', 16), 'The records file is JSON.'];
        yield 'deepest read' => [str_repeat('[', 15).str_repeat(']', 15), 'The keys of records are incidents, maintenances.'];
        yield 'a string' => ['"incidents"', 'The records file holds a JSON object.'];
        yield 'invalid records' => ['{"incidents": 3}', 'The incidents of incidents are a list.'];
    }

    #[DataProvider('refusedFiles')]
    public function testARecordsFileIsRefused(string $contents, string $message): void
    {
        $path = $this->directory.'/records.json';
        file_put_contents($path, $contents);

        $this->expectException(InvalidStatusRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        new JsonFileRecords($path)->maintenances();
    }

    public function testADirectoryTakesEveryFile(): void
    {
        new DirectoryPublisher($this->directory.'/site/')->publish($this->snapshot());

        self::assertSame('{"status":"operational"}', file_get_contents($this->directory.'/site/status.json'));
        self::assertSame('<feed/>', file_get_contents($this->directory.'/site/feed.atom'));
        self::assertSame('<!doctype html>', file_get_contents($this->directory.'/site/index.html'));
        self::assertSame(['.', '..', 'feed.atom', 'index.html', 'status.json'], scandir($this->directory.'/site'));
    }

    public function testAStorageTakesEveryFileUnderItsPrefixWithItsTypeAndVisibility(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        new FlysystemPublisher($storage, '/status/', 'private')->publish($this->snapshot());

        self::assertSame('<!doctype html>', $storage->read('status/index.html'));
        self::assertSame('private', $storage->visibility('status/feed.atom'));
        self::assertSame('{"status":"operational"}', $storage->read('status/status.json'));

        $plain = new Filesystem(new InMemoryFilesystemAdapter());
        new FlysystemPublisher($plain)->publish($this->snapshot());
        self::assertSame('<feed/>', $plain->read('feed.atom'));
        self::assertSame('public', $plain->visibility('feed.atom'));
    }

    public function testTheMediaTypeIsGivenToTheStorage(): void
    {
        $adapter = new InMemoryFilesystemAdapter();
        new FlysystemPublisher(new Filesystem($adapter), 'x')->publish($this->snapshot());

        self::assertSame('application/atom+xml', $adapter->mimeType('x/feed.atom')->mimeType());
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        return [
            'incidents' => [['id' => 'slow', 'title' => 'Slow', 'impact' => 'degraded', 'components' => [], 'updates' => [['at' => '2026-11-12T10:00:00Z', 'state' => 'investigating', 'message' => 'Looking.']]]],
            'maintenances' => [['id' => 'upgrade', 'title' => 'Upgrade', 'components' => [], 'starts_at' => '2026-11-20T02:00:00Z', 'ends_at' => '2026-11-20T03:00:00Z']],
        ];
    }

    private function snapshot(): Snapshot
    {
        return Snapshot::of(Fixtures::now(), '<!doctype html>', '{"status":"operational"}', '<feed/>');
    }

    public function testADirectoryWithASlashAtTheEndIsJoinedWithASingleSlash(): void
    {
        $filesystem = new class extends LocalFilesystem {
            /** @var list<string> */
            public array $paths = [];

            #[Override]
            public function dumpFile(string $filename, mixed $content): void
            {
                $this->paths[] = $filename;
            }
        };

        new DirectoryPublisher('/srv/site/', $filesystem)->publish($this->snapshot());

        self::assertSame(['/srv/site/status.json', '/srv/site/feed.atom', '/srv/site/index.html'], $filesystem->paths);
    }

    public function testAStorageIsWrittenAtTheCleanedPrefixWithTheMediaTypeAndTheVisibility(): void
    {
        $writes = new ArrayObject();
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->method('write')->willReturnCallback(static function (string $path, string $contents, array $config) use ($writes): void {
            $writes[$path] = $config;
        });

        new FlysystemPublisher($storage, '/status/', 'private')->publish($this->snapshot());

        self::assertSame([
            'status/status.json' => ['mimetype' => 'application/json', 'visibility' => 'private'],
            'status/feed.atom' => ['mimetype' => 'application/atom+xml', 'visibility' => 'private'],
            'status/index.html' => ['mimetype' => 'text/html; charset=utf-8', 'visibility' => 'private'],
        ], $writes->getArrayCopy());

        $writes->exchangeArray([]);
        new FlysystemPublisher($storage)->publish($this->snapshot());

        self::assertSame(['status.json' => ['mimetype' => 'application/json'], 'feed.atom' => ['mimetype' => 'application/atom+xml'], 'index.html' => ['mimetype' => 'text/html; charset=utf-8']], $writes->getArrayCopy());
    }
}
