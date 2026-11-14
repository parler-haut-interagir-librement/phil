<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Exception;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\Exception\ExportFailed;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Export\ExportedFact;
use Phil\AuditBundle\Infrastructure\Doctrine\AuditTable;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalAuditRowRepository;
use Phil\AuditBundle\Infrastructure\Doctrine\DbalFeedCheckpoints;
use Phil\AuditBundle\Infrastructure\Export\JsonLinesFactExporter;
use Phil\AuditBundle\Infrastructure\Export\OtlpLogExporter;
use Phil\AuditBundle\Tests\Unit\Fixture\InMemoryAuditRows;
use Phil\AuditBundle\Tests\Unit\Fixture\JsonSerializer;
use Phil\Url\Exception\InvalidUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(JsonLinesFactExporter::class)]
#[CoversClass(OtlpLogExporter::class)]
#[CoversClass(DbalFeedCheckpoints::class)]
#[CoversClass(DbalAuditRowRepository::class)]
#[CoversClass(AuditTable::class)]
final class ExportTest extends TestCase
{
    public function testFactsAreAppendedAsJsonLines(): void
    {
        $file = sys_get_temp_dir().'/phil-audit-facts-'.bin2hex(random_bytes(4)).'.jsonl';
        [$first, $second] = $this->facts();
        $exporter = new JsonLinesFactExporter($file);

        $exporter->export([$first]);
        $exporter->export([$second]);

        $lines = explode("\n", (string) file_get_contents($file));
        self::assertCount(3, $lines);
        self::assertSame('', $lines[2]);
        self::assertSame('{"segment":"orders","sequence":1,"recorded_at":"2026-11-12T09:00:00.000000Z","kind":"event","action":"order.paid","actor":"acct-7","subject_type":"order","subject_id":"42","tenant":"Boutique é/1","hash":"'.$first->hash.'"}', $lines[0]);
        self::assertSame(JsonLinesFactExporter::line($second), $lines[1]);
        self::assertStringContainsString('"tenant":null', $lines[1]);
        unlink($file);

        ob_start();
        new JsonLinesFactExporter('php://output')->export([$first, $second]);
        self::assertSame(JsonLinesFactExporter::line($first)."\n".JsonLinesFactExporter::line($second)."\n", ob_get_clean(), 'A stream is written without a lock.');

        $this->expectException(ExportFailed::class);
        $this->expectExceptionMessageIsOrContains('2 fact(s) could not be appended to /nonexistent/directory/facts.jsonl.');
        new JsonLinesFactExporter('/nonexistent/directory/facts.jsonl')->export([$first, $second]);
    }

    public function testFactsAreSentAsOpenTelemetryLogRecords(): void
    {
        [$first, $second] = $this->facts();
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse('{}', ['http_code' => 200]);
        });
        $exporter = new OtlpLogExporter($client, 'https://collector.internal:4318/v1/logs', 'shop', ['Authorization' => 'Bearer t']);

        $exporter->export([$first, $second]);

        self::assertCount(1, $requests);
        [$method, $url, $options] = $requests[0];
        self::assertSame(['POST', 'https://collector.internal:4318/v1/logs'], [$method, $url]);
        self::assertIsArray($options['headers']);
        self::assertContains('Content-Type: application/json', $options['headers']);
        self::assertContains('Authorization: Bearer t', $options['headers']);
        self::assertSame(10.0, $options['timeout']);
        self::assertSame(0, $options['max_redirects'], 'Redirects are never followed.');
        self::assertSame(json_encode($exporter->body([$first, $second]), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), $options['body']);

        $attribute = static fn (string $key, string $value): array => ['key' => $key, 'value' => ['stringValue' => $value]];
        self::assertSame(['resourceLogs' => [[
            'resource' => ['attributes' => [$attribute('service.name', 'shop')]],
            'scopeLogs' => [[
                'scope' => ['name' => 'phil/audit-bundle'],
                'logRecords' => [
                    [
                        'timeUnixNano' => '1794474000000000000',
                        'severityNumber' => 9,
                        'severityText' => 'INFO',
                        'body' => ['stringValue' => 'order.paid'],
                        'attributes' => [
                            $attribute('phil.audit.segment', 'orders'),
                            ['key' => 'phil.audit.sequence', 'value' => ['intValue' => '1']],
                            $attribute('phil.audit.kind', 'event'),
                            $attribute('phil.audit.action', 'order.paid'),
                            $attribute('phil.audit.actor', 'acct-7'),
                            $attribute('phil.audit.subject_type', 'order'),
                            $attribute('phil.audit.subject_id', '42'),
                            $attribute('phil.audit.tenant', 'Boutique é/1'),
                            $attribute('phil.audit.hash', $first->hash),
                        ],
                    ],
                    [
                        'timeUnixNano' => '1794474000000000000',
                        'severityNumber' => 9,
                        'severityText' => 'INFO',
                        'body' => ['stringValue' => 'discontinuity'],
                        'attributes' => [
                            $attribute('phil.audit.segment', 'orders'),
                            ['key' => 'phil.audit.sequence', 'value' => ['intValue' => '2']],
                            $attribute('phil.audit.kind', 'discontinuity'),
                            $attribute('phil.audit.hash', $second->hash),
                        ],
                    ],
                ],
            ]],
        ]]], $exporter->body([$first, $second]), 'Without an action, the body is the kind of row, and a fact the row lacks is no attribute.');
    }

    public function testACollectorThatRefusesFailsTheBatch(): void
    {
        [$first] = $this->facts();

        foreach ([[199, 'The collector answered 199 to 1 fact(s).'], [300, 'The collector answered 300 to 1 fact(s).'], [503, 'The collector answered 503 to 1 fact(s).']] as [$status, $message]) {
            try {
                new OtlpLogExporter(new MockHttpClient(new MockResponse('', ['http_code' => $status])), 'https://collector.internal/v1/logs', 'shop')->export([$first]);
                self::fail(\sprintf('A %d was taken for sent.', $status));
            } catch (ExportFailed $exportFailed) {
                self::assertSame($message, $exportFailed->getMessage());
            }
        }

        new OtlpLogExporter(new MockHttpClient(new MockResponse('', ['http_code' => 299])), 'https://collector.internal/v1/logs', 'shop')->export([$first]);

        $requests = [];
        new OtlpLogExporter(new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $options['timeout'];

            return new MockResponse('', ['http_code' => 204]);
        }), 'https://collector.internal/v1/logs', 'shop', timeout: 2.5)->export([$first]);
        self::assertSame([2.5], $requests);

        $this->expectException(ExportFailed::class);
        $this->expectExceptionMessageIsOrContains('1 fact(s) could not be sent to the collector: unreachable');
        new OtlpLogExporter(new MockHttpClient(static fn (): never => throw new TransportException('unreachable')), 'https://collector.internal/v1/logs', 'shop')->export([$first]);
    }

    public function testACollectorThatRedirectsFailsTheBatchWithoutFollowing(): void
    {
        [$first] = $this->facts();
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return new MockResponse('', ['http_code' => 307, 'response_headers' => ['Location' => 'https://elsewhere.example/v1/logs']]);
        });

        try {
            new OtlpLogExporter($client, 'https://collector.internal/v1/logs', 'shop', ['Authorization' => 'Bearer t'])->export([$first]);
            self::fail('A redirect was taken for sent.');
        } catch (ExportFailed $exportFailed) {
            self::assertSame('The collector answered 307 to 1 fact(s).', $exportFailed->getMessage());
        }

        self::assertSame(['https://collector.internal/v1/logs'], $requests);
    }

    public function testTheEndpointIsReadByPhilUrl(): void
    {
        [$first] = $this->facts();
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return new MockResponse('', ['http_code' => 200]);
        });

        new OtlpLogExporter($client, ' HTTPS://Collector.Internal:443/v1/logs ', 'shop')->export([$first]);
        new OtlpLogExporter($client, 'http://localhost:4318/v1/logs', 'shop')->export([$first]);

        self::assertSame(['https://collector.internal/v1/logs', 'http://localhost:4318/v1/logs'], $requests);
        self::assertSame('https://collector.internal:4318/v1/logs', OtlpLogExporter::endpoint('https://collector.internal:4318/v1/logs')->toString());

        foreach (['ftp://collector.internal/v1/logs', 'https://user:secret@collector.internal/v1/logs', 'https://10.0.0.7:4318/v1/logs', 'https://collector/v1/logs', 'collector.internal/v1/logs', 'https://collector.internal:0/v1/logs'] as $endpoint) {
            try {
                new OtlpLogExporter($client, $endpoint, 'shop');
                self::fail(\sprintf('%s was taken for an endpoint.', $endpoint));
            } catch (InvalidUrl $invalidUrl) {
                self::assertNotSame('', $invalidUrl->getMessage());
            }
        }

        self::assertCount(2, $requests, 'Nothing is sent to an endpoint refused.');
    }

    public function testTheFeedsPlacesOnlyMoveForward(): void
    {
        $connection = $this->connection();
        $checkpoints = new DbalFeedCheckpoints($connection);

        self::assertSame(0, $checkpoints->position('kpi', 'orders'));
        $checkpoints->advance('kpi', 'orders', 3);
        self::assertSame(3, $checkpoints->position('kpi', 'orders'));
        $checkpoints->advance('kpi', 'orders', 2);
        self::assertSame(3, $checkpoints->position('kpi', 'orders'), 'Never back.');
        $checkpoints->advance('kpi', 'orders', 3);
        $checkpoints->advance('kpi', 'orders', 7);
        self::assertSame(7, $checkpoints->position('kpi', 'orders'));
        $checkpoints->advance('kpi', 'members', 1);
        $checkpoints->advance('siem', 'orders', 0);
        $checkpoints->advance('siem', 'orders', -4);

        self::assertEquals([['kpi', 'members', 1], ['kpi', 'orders', 7]], $connection->fetchAllNumeric('SELECT feed, segment, sequence FROM '.AuditTable::FEED.' ORDER BY feed, segment'));
        self::assertSame(0, $checkpoints->position('siem', 'orders'));
    }

    public function testAPlaceIsNeverBelowZero(): void
    {
        $connection = $this->connection();
        $connection->insert(AuditTable::FEED, ['feed' => 'kpi', 'segment' => 'orders', 'sequence' => -5]);

        self::assertSame(0, new DbalFeedCheckpoints($connection)->position('kpi', 'orders'));

        $unknown = self::createStub(Connection::class);
        $unknown->method('fetchOne')->willReturn(null);
        self::assertSame(0, new DbalFeedCheckpoints($unknown)->position('kpi', 'orders'));
    }

    public function testAPlaceInsertedByAnotherRunIsMovedForwardFromThere(): void
    {
        $driver = new class('duplicate') extends Exception implements DriverException {
            public function getSQLState(): ?string
            {
                return null;
            }
        };
        $connection = $this->createMock(Connection::class);
        $connection->method('insert')->willThrowException(new UniqueConstraintViolationException($driver, null));
        $connection->expects(self::exactly(2))->method('executeStatement')->willReturnOnConsecutiveCalls(0, 1);

        new DbalFeedCheckpoints($connection)->advance('kpi', 'orders', 4);
    }

    public function testRowsAreReadAfterASequenceOnTheKey(): void
    {
        $rows = new DbalAuditRowRepository($this->connection());
        $trail = new AuditTrail($rows, new JsonSerializer(), new MockClock('2026-11-12 09:00 UTC'));

        foreach (range(1, 5) as $order) {
            $trail->record('orders', ['order' => $order]);
        }

        $trail->record('members', ['member' => 1]);

        self::assertSame([3, 4], array_map(static fn (AuditRow $row): int => $row->sequence, $rows->rowsAfter('orders', 2, 2)));
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn (AuditRow $row): int => $row->sequence, $rows->rowsAfter('orders', 0, 500)));
        self::assertSame([], $rows->rowsAfter('orders', 5, 10));
        self::assertSame([], $rows->rowsAfter('payments', 0, 10));
        self::assertTrue($rows->rowsAfter('orders', 4, 1)[0]->isIntact());
    }

    public function testBothTablesAreAddedOnce(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = AuditTable::configure($connection->createSchemaManager()->introspectSchema(), $connection->getDatabasePlatform());

        self::assertTrue($schema->hasTable(AuditTable::FEED));
        self::assertSame(['feed', 'segment'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), $schema->getTable(AuditTable::FEED)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame($schema, AuditTable::configure($schema, $connection->getDatabasePlatform()));
    }

    /**
     * @return array{ExportedFact, ExportedFact}
     */
    private function facts(): array
    {
        $trail = new AuditTrail(new InMemoryAuditRows(), new JsonSerializer(), new MockClock('2026-11-12 09:00 UTC'));

        return [
            ExportedFact::of($trail->record('orders', ['email' => 'ada@example.org'], Facts::of('order.paid', 'acct-7', Subject::of('order', '42'), 'Boutique é/1'))),
            ExportedFact::of($trail->recordDiscontinuity('orders', 'restored')),
        ];
    }

    private function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach (AuditTable::configure($connection->createSchemaManager()->introspectSchema(), $connection->getDatabasePlatform())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        return $connection;
    }
}
