<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\Checkin\CheckpointId;
use Phil\CheckinBundle\Application\Event\CheckInRecorded;
use Phil\CheckinBundle\Infrastructure\Doctrine\DoctrineCheckInLedger;
use Phil\CheckinBundle\Infrastructure\Http\DeskConfig;
use Phil\CheckinBundle\PhilCheckinBundle;
use Phil\CheckinBundle\Tests\Functional\App\RecordedScans;
use Phil\CheckinBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Two doors of one hall, through the routes, the Doctrine ledger on SQLite and the stateless CSRF protection: a scan
 * online, the same ticket refused at the other door, a ticket the hall's rule refuses, a manifest, a journal
 * synchronised, and the scans recorded heard by a listener of the application.
 */
#[CoversClass(PhilCheckinBundle::class)]
final class CheckinBundleTest extends WebTestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $ledger = new DoctrineCheckInLedger($connection);

        foreach ($ledger->configureSchema(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function testTwoDoorsOfOneHall(): void
    {
        $config = self::getContainer()->get('test.'.DeskConfig::class);
        self::assertInstanceOf(DeskConfig::class, $config);
        self::assertSame(['scan' => '/_checkin/scan', 'manifest' => '/_checkin/manifest', 'sync' => '/_checkin/sync'], $config->for(CheckpointId::of('hall'))['urls']);
        $cookie = $config->for(CheckpointId::of('hall'))['csrfCookie'];

        self::assertSame([200, 'admitted'], $this->post($cookie, 'scan', ['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-1'], 'outcome'));
        self::assertSame([200, 'already_admitted'], $this->post($cookie, 'scan', ['code' => 'CODE-1', 'checkpoint' => 'hall', 'device' => 'door-2'], 'outcome'));
        self::assertSame([403, 'forbidden'], $this->post($cookie, 'scan', ['code' => 'CODE-1', 'checkpoint' => 'stage', 'device' => 'door-1'], 'error'));
        self::assertSame([403, 'forbidden'], $this->post('wrong-cookie', 'scan', ['code' => 'CODE-2', 'checkpoint' => 'hall', 'device' => 'door-1'], 'error'));

        self::assertSame([200, 'wrong_checkpoint'], $this->post($cookie, 'scan', ['code' => 'CODE-2', 'checkpoint' => 'hall', 'device' => 'door-1'], 'outcome'));

        $this->client->request('GET', '/_checkin/manifest?checkpoint=hall&device=door-3', server: ['HTTP_X_PHIL_CHECKIN' => '1']);
        self::assertResponseIsSuccessful();
        $manifest = $this->json();
        self::assertSame('hall', $manifest['checkpoint']);
        self::assertCount(1, (array) $manifest['credentials'], 'the rule of the hall leaves the ticket without a label out');
        self::assertSame([1, true], \array_slice((array) ((array) $manifest['credentials'])[0], 6, 2));

        $scan = ['id' => 'offline-1', 'credential' => 'T-2', 'direction' => 'in', 'outcome' => 'admitted', 'scannedAt' => 1_794_132_000 - 60];
        self::assertSame([200, [['id' => 'offline-1', 'status' => 'recorded']]], $this->post($cookie, 'sync', ['checkpoint' => 'hall', 'device' => 'door-3', 'scans' => [$scan]], 'scans'));
        self::assertSame([200, [['id' => 'offline-1', 'status' => 'duplicate']]], $this->post($cookie, 'sync', ['checkpoint' => 'hall', 'device' => 'door-3', 'scans' => [$scan]], 'scans'));

        $listener = self::getContainer()->get(RecordedScans::class);
        self::assertInstanceOf(RecordedScans::class, $listener);
        self::assertSame(
            [['T-1', 'admitted', false], ['T-1', 'already_admitted', false], ['T-2', 'wrong_checkpoint', false], ['T-2', 'admitted', true]],
            array_map(static fn (CheckInRecorded $scan): array => [$scan->credentialId, $scan->outcome->value, $scan->offline], $listener->scans),
        );
    }

    public function testTheControllerAndTheStylesheetAreServedByAssetMapper(): void
    {
        $mapper = self::getContainer()->get('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $mapper);

        self::assertNotNull($mapper->getAsset('@phil/checkin-bundle/checkin_desk_controller.js'));
        self::assertNotNull($mapper->getAsset('@phil/checkin-bundle/lib/store.js'));
        $stylesheet = $mapper->getAsset('phil-checkin/checkin-desk.css');
        self::assertNotNull($stylesheet);
        self::assertStringStartsWith('/assets/phil-checkin/checkin-desk-', $stylesheet->publicPath);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{int, mixed}
     */
    private function post(string $cookie, string $endpoint, array $body, string $key): array
    {
        $this->client->getHistory()->clear();
        $this->client->getCookieJar()->set(new Cookie($cookie.'_'.self::TOKEN, $cookie, null, '/', 'localhost'));
        $this->client->request('POST', '/_checkin/'.$endpoint, server: ['HTTP_X_PHIL_CHECKIN' => '1', 'HTTP_'.strtoupper(str_replace('-', '_', $cookie)) => self::TOKEN, 'CONTENT_TYPE' => 'application/json'], content: json_encode($body, \JSON_THROW_ON_ERROR));

        return [$this->client->getResponse()->getStatusCode(), $this->json()[$key] ?? null];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(): array
    {
        $json = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);

        return $json;
    }
}
