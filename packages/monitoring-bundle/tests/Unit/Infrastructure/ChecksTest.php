<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use Override;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Infrastructure\Check\CachePoolCheck;
use Phil\MonitoringBundle\Infrastructure\Check\DatabaseCheck;
use Phil\MonitoringBundle\Infrastructure\Check\FilesystemCheck;
use Phil\MonitoringBundle\Infrastructure\Check\FlysystemCheck;
use Phil\MonitoringBundle\Infrastructure\Check\MessengerTransportCheck;
use Phil\MonitoringBundle\Tests\Unit\Support\FakeReceiver;
use Phil\MonitoringBundle\Tests\Unit\Support\PlainReceiver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;
use Symfony\Component\Messenger\Envelope;

#[CoversClass(DatabaseCheck::class)]
#[CoversClass(CachePoolCheck::class)]
#[CoversClass(MessengerTransportCheck::class)]
#[CoversClass(FilesystemCheck::class)]
#[CoversClass(FlysystemCheck::class)]
final class ChecksTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-monitoring-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        new LocalFilesystem()->remove($this->directory);
    }

    public function testADatabaseThatAnswers(): void
    {
        $check = new DatabaseCheck(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

        self::assertSame('database', $check->name());
        self::assertSame(HealthStatus::Up, $check->check()->status);
        self::assertSame('Answers.', $check->check()->summary);
        self::assertSame('database.audit', new DatabaseCheck(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), 'database.audit')->name());
    }

    public function testADatabaseThatCannotBeReachedThrows(): void
    {
        $check = new DatabaseCheck(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->directory.'/missing/database.sqlite']));

        $this->expectException(Exception::class);

        $check->check();
    }

    public function testACachePoolThatKeepsAValueAndForgetsTheProbe(): void
    {
        $pool = new ArrayAdapter();
        $check = new CachePoolCheck($pool, 'cache.app');

        self::assertSame('cache.app', $check->name());
        self::assertSame(HealthStatus::Up, $check->check()->status);
        self::assertSame('Keeps a value.', $check->check()->summary);
        self::assertSame([], $pool->getValues());
    }

    public function testACachePoolThatLosesTheValue(): void
    {
        $outcome = new CachePoolCheck(new NullAdapter(), 'cache.app')->check();

        self::assertSame(HealthStatus::Down, $outcome->status);
        self::assertSame('Loses the value it saved.', $outcome->summary);
    }

    public function testACachePoolThatRefusesToSave(): void
    {
        $pool = new class extends ArrayAdapter {
            #[Override]
            public function save(CacheItemInterface $item): bool
            {
                return false;
            }
        };

        $outcome = new CachePoolCheck($pool, 'cache.app')->check();

        self::assertSame(HealthStatus::Down, $outcome->status);
        self::assertSame('Refuses to save a value.', $outcome->summary);
    }

    public function testTheProbeExpiresInAMinute(): void
    {
        $pool = new class extends ArrayAdapter {
            /**
             * @var list<CacheItemInterface>
             */
            public array $saved = [];

            #[Override]
            public function save(CacheItemInterface $item): bool
            {
                $this->saved[] = $item;

                return parent::save($item);
            }
        };

        new CachePoolCheck($pool, 'cache.app')->check();

        self::assertCount(1, $pool->saved);
        $item = $pool->saved[0];
        self::assertInstanceOf(CacheItem::class, $item);
        self::assertStringStartsWith('phil_monitoring.probe.', $item->getKey());
        self::assertSame(substr($item->getKey(), \strlen('phil_monitoring.probe.')), $item->get());
        $expiry = (fn (): ?float => $this->expiry)->call($item);
        self::assertEqualsWithDelta(microtime(true) + 60, $expiry, 0.5);
    }

    public function testAMessengerTransportUnderAndOverItsThreshold(): void
    {
        $receiver = new FakeReceiver([new Envelope(new stdClass()), new Envelope(new stdClass())]);

        self::assertSame('messenger.async', new MessengerTransportCheck($receiver, 'messenger.async')->name());
        self::assertSame('2 messages waiting.', new MessengerTransportCheck($receiver, 'messenger.async')->check()->summary);
        self::assertSame(HealthStatus::Up, new MessengerTransportCheck($receiver, 'messenger.async', 2)->check()->status);

        $over = new MessengerTransportCheck($receiver, 'messenger.failed', 1)->check();
        self::assertSame(HealthStatus::Degraded, $over->status);
        self::assertSame('2 messages waiting, over 1.', $over->summary);

        $uncounted = new MessengerTransportCheck(new PlainReceiver(), 'messenger.sync', 0)->check();
        self::assertSame(HealthStatus::Up, $uncounted->status);
        self::assertSame('Cannot count its messages.', $uncounted->summary);
    }

    public function testADirectoryThatIsWritable(): void
    {
        $check = new FilesystemCheck($this->directory, 'filesystem.uploads');

        self::assertSame('filesystem.uploads', $check->name());
        self::assertSame(HealthStatus::Up, $check->check()->status);
        self::assertSame('Writable.', $check->check()->summary);
    }

    public function testADirectoryWithItsFreeSpace(): void
    {
        $asked = [];
        $freeSpace = static function (string $directory) use (&$asked): float {
            $asked[] = $directory;

            return 512.0 * 1_048_576 + 1_048_575;
        };

        $enough = new FilesystemCheck($this->directory, 'filesystem.uploads', 512, $freeSpace)->check();
        self::assertSame(HealthStatus::Up, $enough->status);
        self::assertSame('Writable, 512 MB free.', $enough->summary);
        self::assertSame([$this->directory], $asked);

        $short = new FilesystemCheck($this->directory, 'filesystem.uploads', 513, $freeSpace)->check();
        self::assertSame(HealthStatus::Degraded, $short->status);
        self::assertSame('Writable, 512 MB free, under 513.', $short->summary);
    }

    public function testTheFreeSpaceOfTheDiskByDefault(): void
    {
        self::assertMatchesRegularExpression('/^Writable, \d+ MB free\.$/', new FilesystemCheck($this->directory, 'filesystem.uploads', 0)->check()->summary);
    }

    public function testADirectoryThatIsMissingOrReadOnly(): void
    {
        $missing = new FilesystemCheck($this->directory.'/missing', 'filesystem.uploads')->check();
        self::assertSame(HealthStatus::Down, $missing->status);
        self::assertSame('Missing.', $missing->summary);

        chmod($this->directory, 0o500);
        clearstatcache();

        if (is_writable($this->directory)) {
            chmod($this->directory, 0o700);
            self::markTestSkipped('The permissions do not bind this user, root in a container.');
        }

        try {
            $readOnly = new FilesystemCheck($this->directory, 'filesystem.uploads')->check();
        } finally {
            chmod($this->directory, 0o700);
        }

        self::assertSame(HealthStatus::Down, $readOnly->status);
        self::assertSame('Not writable.', $readOnly->summary);
    }

    public function testAStorageThatKeepsAFileAndForgetsTheProbe(): void
    {
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $check = new FlysystemCheck($storage, 'storage.media');

        self::assertSame('storage.media', $check->name());
        self::assertSame(HealthStatus::Up, $check->check()->status);
        self::assertSame('Keeps a file.', $check->check()->summary);
        self::assertFalse($storage->fileExists('.phil-monitoring-probe'));
        self::assertSame([], $storage->listContents('/')->toArray(), 'No probe is left.');
    }

    public function testConcurrentChecksEachWriteAProbeOfTheirOwn(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter {
            /** @var list<string> */
            public array $paths = [];

            public ?FlysystemCheck $check = null;

            public ?CheckOutcome $concurrent = null;

            #[Override]
            public function write(string $path, string $contents, Config $config): void
            {
                $this->paths[] = $path;
                parent::write($path, $contents, $config);

                $check = $this->check;
                $this->check = null;

                if ($check instanceof FlysystemCheck) {
                    $this->concurrent = $check->check();
                }
            }
        };
        $storage = new Filesystem($adapter);
        $check = new FlysystemCheck($storage, 'storage.media');
        $adapter->check = $check;

        $outcome = $check->check();

        self::assertSame([HealthStatus::Up, HealthStatus::Up], [$outcome->status, $adapter->concurrent?->status], 'A check run while another is under way disturbs neither.');
        self::assertCount(2, $adapter->paths);
        self::assertNotSame($adapter->paths[0], $adapter->paths[1]);

        foreach ($adapter->paths as $path) {
            self::assertMatchesRegularExpression('/^\.phil-monitoring-probe-[0-9a-f]{16}$/D', $path);
        }

        self::assertSame([], $storage->listContents('/')->toArray());
    }

    public function testTheProbeIsDeletedWhenItCannotBeReadBack(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter {
            #[Override]
            public function read(string $path): string
            {
                throw UnableToReadFile::fromLocation($path, 'unreachable');
            }
        };
        $storage = new Filesystem($adapter);

        try {
            new FlysystemCheck($storage, 'storage.media')->check();
            self::fail('The failure reaches the caller, who counts the check as down.');
        } catch (UnableToReadFile) {
        }

        self::assertSame([], $storage->listContents('/')->toArray(), 'The probe is deleted all the same.');
    }

    public function testAStorageThatLosesTheFile(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter {
            #[Override]
            public function read(string $path): string
            {
                return 'another';
            }
        };

        $outcome = new FlysystemCheck(new Filesystem($adapter), 'storage.media')->check();

        self::assertSame(HealthStatus::Down, $outcome->status);
        self::assertSame('Loses the file it wrote.', $outcome->summary);
    }
}
