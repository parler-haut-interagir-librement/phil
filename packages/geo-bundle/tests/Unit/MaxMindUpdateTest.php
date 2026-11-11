<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Unit;

use Override;
use Phil\GeoBundle\Infrastructure\Console\UpdateMaxMindDatabaseCommand;
use Phil\GeoBundle\Infrastructure\MaxMind\DatabaseUpdater;
use Phil\GeoBundle\Infrastructure\MaxMind\UpdateFailed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(DatabaseUpdater::class)]
#[CoversClass(UpdateFailed::class)]
#[CoversClass(UpdateMaxMindDatabaseCommand::class)]
final class MaxMindUpdateTest extends TestCase
{
    private string $directory;

    private string $database;

    /** @var list<array{string, string}> */
    private array $requests = [];

    /** @var array<string, MockResponse> */
    private array $responses = [];

    private string $checksum = '';

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-geo-update-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->database = $this->directory.'/GeoLite2-City.mmdb';
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    /**
     * The archive is written next to the database, under a name of its own, while it arrives; only the database, its
     * checksum and the lock are left.
     */
    public function testTheLatestDatabaseIsPutInPlace(): void
    {
        $mmdb = self::mmdb('GeoLite2-City');
        $archive = (string) gzencode($this->tar([
            'GeoLite2-City_20261104/' => null,
            'GeoLite2-City_20261104/LICENSE.txt' => str_repeat('L', 700),
            'GeoLite2-City_20261104/other.mmdb' => self::mmdb('Other'),
            'GeoLite2-City_20261104/trap.tar' => $this->tar(['GeoLite2-City.mmdb' => self::mmdb('Trap')]),
            'GeoLite2-City_20261104/GeoLite2-City.mmdb' => $mmdb,
        ]));
        $this->serve($archive);
        $seen = [];
        $this->responses['archive'] = new MockResponse((function () use ($archive, &$seen): iterable {
            yield substr($archive, 0, 10);
            $seen = $this->files();

            yield substr($archive, 10);
        })());
        file_put_contents($this->database, 'old');

        $metadata = $this->updater()->update();

        self::assertNotNull($metadata);
        self::assertSame(['GeoLite2-City', '2026-11-04'], [$metadata->databaseType, $metadata->builtAt->format('Y-m-d')]);
        $temporary = array_values(array_diff($seen, [$this->database, $this->database.'.lock']));
        self::assertCount(4, $seen);
        self::assertCount(2, $temporary);
        self::assertMatchesRegularExpression('~/GeoLite2-City\.mmdb\.[^/]+$~', $temporary[0]);
        self::assertMatchesRegularExpression('~/GeoLite2-City\.mmdb\.[^/]+$~', $temporary[1]);
        self::assertSame($mmdb, file_get_contents($this->database));
        self::assertSame(0o666 & ~umask(), fileperms($this->database) & 0o777);
        self::assertSame($this->checksum."\n", file_get_contents($this->database.'.sha256'));
        self::assertSame([$this->database, $this->database.'.lock', $this->database.'.sha256'], $this->files());
        self::assertSame([
            ['GET', 'https://download.example.com/databases/GeoLite2-City/download?suffix=tar.gz.sha256'],
            ['GET', 'https://download.example.com/databases/GeoLite2-City/download?suffix=tar.gz'],
        ], $this->requests);
    }

    public function testTheLatestIsNotDownloadedAgainUnlessForced(): void
    {
        $this->serve($this->archive(['GeoLite2-City.mmdb' => self::mmdb('GeoLite2-City')]));
        $this->updater()->update();
        $before = \count($this->requests);

        self::assertNull($this->updater()->update());
        self::assertCount($before + 1, $this->requests);
        self::assertNotNull($this->updater()->update(true));
        self::assertCount($before + 3, $this->requests);

        unlink($this->database);
        self::assertNotNull($this->updater()->update());
        file_put_contents($this->database.'.sha256', str_repeat('0', 64));
        self::assertNotNull($this->updater()->update());
    }

    public function testTheAccountIsSentAsBasicAuth(): void
    {
        $this->serve($this->archive(['GeoLite2-City.mmdb' => self::mmdb('GeoLite2-City')]));
        $headers = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$headers): MockResponse {
            $normalized = \is_array($options['normalized_headers'] ?? null) ? $options['normalized_headers'] : [];
            $authorization = \is_array($normalized['authorization'] ?? null) ? $normalized['authorization'] : [];
            $headers[] = \is_string($authorization[0] ?? null) ? $authorization[0] : '';

            return str_ends_with($url, 'sha256') ? $this->responses['sha256'] : $this->responses['archive'];
        });

        new DatabaseUpdater($client, $this->database, '12345', 'secret')->update();

        self::assertSame(array_fill(0, 2, 'Authorization: Basic '.base64_encode('12345:secret')), $headers);
    }

    public function testTheDefaults(): void
    {
        $this->serve($this->archive(['GeoLite2-City.mmdb' => self::mmdb('GeoLite2-City')]));
        new DatabaseUpdater($this->client(), $this->database, '1', 'k')->update();

        self::assertSame('https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz.sha256', $this->requests[0][1]);
        self::assertSame(['GeoLite2-City', 'https://download.maxmind.com/geoip/databases'], [DatabaseUpdater::EDITION, DatabaseUpdater::BASE_URL]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function failures(): iterable
    {
        yield 'no account' => [['account' => null], 'set phil_geo.ip_locator.maxmind.account_id and license_key, those of the MaxMind account.'];
        yield 'no licence key' => [['key' => null], 'set phil_geo.ip_locator.maxmind.account_id and license_key, those of the MaxMind account.'];
        yield 'checksum refused' => [['sha256' => new MockResponse('Unauthorized', ['http_code' => 401])], 'MaxMind answered HTTP 401 for the checksum; check the account and the licence key.'];
        yield 'archive missing' => [['archive' => new MockResponse('Not found', ['http_code' => 404])], 'MaxMind answered HTTP 404 for the archive; check the account and the licence key.'];
        yield 'no checksum' => [['sha256' => new MockResponse('nothing here')], 'MaxMind answered no checksum.'];
        yield 'checksum without a name' => [['sha256' => new MockResponse(str_repeat('a', 64))], 'MaxMind answered no checksum.'];
        yield 'checksum after other words' => [['sha256' => new MockResponse('x'.str_repeat('a', 64).'  name')], 'MaxMind answered no checksum.'];
        yield 'another archive' => [['checksum' => str_repeat('a', 64)], 'the archive does not match the checksum MaxMind published.'];
        yield 'no database in it' => [['files' => ['README.txt' => 'hello']], 'the archive holds no GeoLite2-City.mmdb file.'];
        yield 'a database of another name' => [['files' => ['other.mmdb' => self::mmdb('GeoLite2-City')]], 'the archive holds no GeoLite2-City.mmdb file.'];
        yield 'a directory of its name' => [['files' => ['GeoLite2-City.mmdb' => null]], 'the archive holds no GeoLite2-City.mmdb file.'];
        yield 'a database after the end' => [['after' => true], 'the archive holds no GeoLite2-City.mmdb file.'];
        yield 'not a database' => [['files' => ['GeoLite2-City.mmdb' => 'garbage']], 'The MaxMind database'];
        yield 'an empty database' => [['files' => ['GeoLite2-City.mmdb' => '']], 'The MaxMind database'];
        yield 'another edition' => [['files' => ['GeoLite2-City.mmdb' => self::mmdb('GeoLite2-Country')]], 'the archive holds a GeoLite2-Country database, not GeoLite2-City.'];
        yield 'unreachable' => [['sha256' => new MockResponse('', ['error' => 'host unreachable'])], 'host unreachable'];
        yield 'cut off' => [['archive' => new MockResponse(['abc', new RuntimeException('cut')])], 'cut'];
        yield 'no directory' => [['database' => '/none/GeoLite2-City.mmdb'], 'the directory "/none" does not exist or cannot be written.'];
        yield 'a directory read only' => [['read only' => true], 'locked" does not exist or cannot be written.'];
        yield 'the lock in the way' => [['in the way' => '.lock'], 'GeoLite2-City.mmdb.lock" cannot be written.'];
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('failures')]
    public function testAFailedUpdateKeepsTheDatabaseInPlace(array $case, string $reason): void
    {
        $files = ['GeoLite2-City.mmdb' => self::mmdb('GeoLite2-City')];

        if (\is_array($case['files'] ?? null)) {
            $files = [];

            foreach ($case['files'] as $name => $content) {
                $files[(string) $name] = \is_string($content) ? $content : null;
            }
        }

        $tar = isset($case['after']) ? $this->tar(['README.txt' => 'hi']).$this->tar($files) : $this->tar($files);
        $this->serve((string) gzencode($tar), \is_string($case['checksum'] ?? null) ? $case['checksum'] : null);

        foreach (['sha256', 'archive'] as $key) {
            if (($case[$key] ?? null) instanceof MockResponse) {
                $this->responses[$key] = $case[$key];
            }
        }

        $database = \is_string($case['database'] ?? null) ? $case['database'] : $this->database;

        if (isset($case['read only'])) {
            mkdir($this->directory.'/locked');
            chmod($this->directory.'/locked', 0o555);
            $database = $this->directory.'/locked/GeoLite2-City.mmdb';
        }

        if (\is_string($case['in the way'] ?? null)) {
            mkdir($this->database.$case['in the way']);
        }

        file_put_contents($this->database, 'old');
        $account = \array_key_exists('account', $case) ? null : '1';
        $key = \array_key_exists('key', $case) ? null : 'k';

        try {
            new DatabaseUpdater($this->client(), $database, $account, $key, 'GeoLite2-City', 'https://download.example.com/databases')->update();
            self::fail($reason);
        } catch (UpdateFailed $updateFailed) {
            self::assertStringStartsWith('The MaxMind database was not updated: ', $updateFailed->getMessage());
            self::assertStringContainsString($reason, $updateFailed->getMessage());
        }

        self::assertSame('old', file_get_contents($this->database));
        self::assertSame([$this->database], array_values(array_diff($this->files(), [$this->database.'.lock'])));
    }

    /**
     * A second run — a cron and a forced update, two hosts on shared storage — fails at once and touches nothing of
     * the first; the first, done, lets the next one run.
     */
    public function testOneUpdateRunsAtATime(): void
    {
        $this->serve($this->archive(['GeoLite2-City.mmdb' => self::mmdb('GeoLite2-City')]));
        file_put_contents($this->database, 'old');
        $running = fopen($this->database.'.lock', 'c');
        self::assertNotFalse($running);
        self::assertTrue(flock($running, \LOCK_EX));

        try {
            $this->updater()->update();
            self::fail('The update ran beside another.');
        } catch (UpdateFailed $updateFailed) {
            self::assertSame('The MaxMind database was not updated: another update of this database is running.', $updateFailed->getMessage());
        }

        self::assertSame([], $this->requests);
        self::assertSame('old', file_get_contents($this->database));
        fclose($running);

        self::assertNotNull($this->updater()->update());
        self::assertNotNull($this->updater()->update(true));
    }

    /**
     * Files left by a run that was killed, or of the names the update once used, are neither read nor removed.
     */
    public function testFilesOfAnotherRunAreLeftAlone(): void
    {
        $this->serve($this->archive(['GeoLite2-City.mmdb' => self::mmdb('GeoLite2-City')]));
        file_put_contents($this->database.'.tar.gz', 'another run');
        mkdir($this->database.'.new');

        self::assertNotNull($this->updater()->update());
        self::assertSame('another run', file_get_contents($this->database.'.tar.gz'));
        self::assertDirectoryExists($this->database.'.new');
        self::assertSame([$this->database, $this->database.'.lock', $this->database.'.sha256', $this->database.'.tar.gz'], $this->files());
    }

    public function testTheCommandSaysWhatItDid(): void
    {
        $this->serve($this->archive(['GeoLite2-City.mmdb' => self::mmdb('GeoLite2-City')]));

        self::assertSame([Command::SUCCESS, "The MaxMind database GeoLite2-City built on 2026-11-04 is in place.\n"], $this->command([]));
        self::assertSame([Command::SUCCESS, "The MaxMind database is already the latest.\n"], $this->command([]));
        self::assertSame([Command::SUCCESS, "The MaxMind database GeoLite2-City built on 2026-11-04 is in place.\n"], $this->command(['--force' => true]));

        $this->responses['sha256'] = new MockResponse('Unauthorized', ['http_code' => 401]);
        $refused = 'The MaxMind database was not updated: MaxMind answered HTTP 401 for the checksum; check the account and the licence key.';
        self::assertSame([Command::FAILURE, $refused."\n"], $this->command([]));
        self::assertSame([Command::FAILURE, "\e[37;41m".$refused."\e[39;49m\n"], $this->command([], true));
        self::assertSame(['phil:geo:maxmind:update', 'Downloads the latest MaxMind database and puts it in place'], [new UpdateMaxMindDatabaseCommand($this->updater())->getName(), new UpdateMaxMindDatabaseCommand($this->updater())->getDescription()]);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{int, string}
     */
    private function command(array $input, bool $decorated = false): array
    {
        $tester = new CommandTester(new UpdateMaxMindDatabaseCommand($this->updater()));

        return [$tester->execute($input, ['decorated' => $decorated]), $tester->getDisplay()];
    }

    private function updater(): DatabaseUpdater
    {
        return new DatabaseUpdater($this->client(), $this->database, '1', 'k', 'GeoLite2-City', 'https://download.example.com/databases/');
    }

    private function client(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->requests[] = [$method, $url];

            return clone (str_ends_with($url, 'sha256') ? $this->responses['sha256'] : $this->responses['archive']);
        });
    }

    private function serve(string $archive, ?string $checksum = null): void
    {
        $this->checksum = $checksum ?? hash('sha256', $archive);
        $this->responses = [
            'sha256' => new MockResponse($this->checksum."  GeoLite2-City_20261104.tar.gz\n"),
            'archive' => new MockResponse($archive),
        ];
    }

    /**
     * @return list<string> the files of the directory, directories left out
     */
    private function files(): array
    {
        $files = glob($this->directory.'/*');

        return array_values(array_filter(false === $files ? [] : $files, is_file(...)));
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            unlink($path);

            return;
        }

        chmod($path, 0o755);
        $entries = glob($path.'/*');

        foreach (false === $entries ? [] : $entries as $entry) {
            $this->remove($entry);
        }

        rmdir($path);
    }

    /**
     * @param array<string, ?string> $files
     */
    private function archive(array $files): string
    {
        return (string) gzencode($this->tar($files));
    }

    /**
     * A tar archive, its headers filled as tar writes them, ended by two blocks of zeros; a null content is a
     * directory.
     *
     * @param array<string, ?string> $files
     */
    private function tar(array $files): string
    {
        $tar = '';

        foreach ($files as $name => $content) {
            $size = \strlen($content ?? '');
            $header = str_pad($name, 100, "\0").implode("\x00", ['0000644', '0001750', '0001750', \sprintf('%011o', $size), '15017125340', '        '.(null === $content ? '5' : '0')]);
            $tar .= str_pad($header, 512, "\0").$content.str_repeat("\0", (512 - $size % 512) % 512);
        }

        return $tar.str_repeat("\0", 1_024);
    }

    /**
     * The smallest MaxMind database: one node, no network, and its metadata.
     */
    private static function mmdb(string $type): string
    {
        $metadata = ['binary_format_major_version' => 2, 'build_epoch' => 1_793_750_400, 'database_type' => $type, 'ip_version' => 6, 'node_count' => 1, 'record_size' => 24];
        $map = pack('C', 0xE0 | \count($metadata));

        foreach ($metadata as $key => $value) {
            $map .= pack('C', 0x40 | \strlen($key)).$key.(\is_string($value) ? pack('C', 0x40 | \strlen($value)).$value : "\xC4".pack('N', $value));
        }

        return "\x00\x00\x01\x00\x00\x01".str_repeat("\0", 16)."\xAB\xCD\xEFMaxMind.com".$map;
    }
}
