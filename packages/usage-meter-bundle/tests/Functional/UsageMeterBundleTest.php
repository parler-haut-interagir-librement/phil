<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\UsageMeter\UsageMeter;
use Phil\UsageMeterBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversNothing]
final class UsageMeterBundleTest extends WebTestCase
{
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
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $connection = $entityManager->getConnection();

        // The tables come from the schema the ORM generates, as `doctrine:migrations:diff` would write them.
        $schema = new SchemaTool($entityManager)->getSchemaFromMetadata([]);
        self::assertTrue($schema->hasTable('phil_usage_counter'));
        self::assertTrue($schema->hasTable('phil_usage_key'));

        foreach (['phil_usage_counter', 'phil_usage_key'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function testRequestsAreCountedByIpAddressUntil429(): void
    {
        foreach (['2', '1', '0'] as $remaining) {
            $this->client->request('GET', '/search', server: ['REMOTE_ADDR' => '203.0.113.7']);

            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('RateLimit-Limit', '3');
            self::assertResponseHeaderSame('RateLimit-Remaining', $remaining);
        }

        $this->client->request('GET', '/search', server: ['REMOTE_ADDR' => '203.0.113.7']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertResponseHeaderSame('RateLimit-Remaining', '0');

        $this->client->request('GET', '/search', server: ['REMOTE_ADDR' => '198.51.100.2']);

        self::assertResponseIsSuccessful();

        $connection = $this->service('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $subjects = $connection->fetchFirstColumn('SELECT subject FROM phil_usage_counter ORDER BY used DESC');
        self::assertCount(2, $subjects);
        self::assertSame('ip:'.hash_hmac('sha256', '203.0.113.7', hash_hkdf('sha256', 'test', 32, 'phil_rate_limit.client_ip_key')), $subjects[0]);
    }

    public function testRequestsAreCountedByTheApplicationsSubject(): void
    {
        foreach ([200, 200, 429] as $status) {
            $this->client->request('GET', '/export', server: ['HTTP_X_ACCOUNT' => 'alice']);
            self::assertResponseStatusCodeSame($status);
        }

        $this->client->request('GET', '/export');
        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('RateLimit-Limit');

        $meter = $this->service(UsageMeter::class);
        self::assertInstanceOf(UsageMeter::class, $meter);
        self::assertSame(4, $meter->usage('alice', 'exports')->used);
    }

    public function testThePurgeCommand(): void
    {
        $kernel = $this->client->getKernel();
        $tester = new CommandTester(new Application($kernel)->find('phil:usage-meter:purge'));

        self::assertSame(0, $tester->execute([]));
        self::assertSame("0 counter(s) and key(s) forgotten.\n", $tester->getDisplay());
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
