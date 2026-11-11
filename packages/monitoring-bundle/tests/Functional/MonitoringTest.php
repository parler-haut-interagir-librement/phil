<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Functional;

use Override;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Application\Maintenance;
use Phil\MonitoringBundle\Application\Message\WatchHeartbeats;
use Phil\MonitoringBundle\Domain\Contract\HeartbeatStore;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\MonitoringBundle\Infrastructure\Http\QueuesController;
use Phil\MonitoringBundle\PhilMonitoringBundle;
use Phil\MonitoringBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The bundle in a small application: the endpoints of the monitors, the maintenance mode, the heartbeats and the
 * commands, wired by the bundle and served by the kernel.
 */
#[CoversClass(PhilMonitoringBundle::class)]
final class MonitoringTest extends WebTestCase
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
        $this->client->disableReboot();
        new Filesystem()->remove($this->kernel()->maintenanceFile());
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->kernel()->maintenanceFile());
        parent::tearDown();
    }

    public function testTheEndpointsOfTheMonitors(): void
    {
        $this->client->request('GET', '/_health/live');
        self::assertResponseStatusCodeSame(200);
        self::assertSame('{"status":"up"}', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/_health/ready');
        self::assertResponseStatusCodeSame(200);
        self::assertSame('{"status":"up"}', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/_health/details');
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/_health/details', server: ['HTTP_AUTHORIZATION' => 'Bearer s3cret']);
        self::assertResponseStatusCodeSame(200);
        $details = json_decode((string) $this->client->getResponse()->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($details);
        self::assertIsArray($details['checks']);
        self::assertSame(['heartbeats', 'cache.app', 'messenger.async', 'messenger.failed'], array_column($details['checks'], 'name'));
        self::assertSame(['up', 'up', 'up', 'up'], array_column($details['checks'], 'status'));
    }

    public function testTheMaintenanceModeFromTheCommandToTheVisitor(): void
    {
        $command = new CommandTester(new Application($this->kernel())->find('phil:monitoring:maintenance'));
        self::assertSame(0, $command->execute(['action' => 'enable', '--retry-after' => '120']));

        $this->client->request('GET', '/');
        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Retry-After', '120');
        self::assertStringContainsString('Please come back in 2 minutes.', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/', server: ['REMOTE_ADDR' => '10.0.0.1']);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/_health/ready');
        self::assertResponseIsSuccessful();

        self::assertSame(0, $command->execute(['action' => 'disable']));
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertNull($this->service(Maintenance::class, Maintenance::class)->current());
    }

    public function testATaskLateMakesTheSiteDegraded(): void
    {
        $this->service(Heartbeats::class, Heartbeats::class)->beat('backup');
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->modify('+2 hours');

        $watch = new CommandTester(new Application($this->kernel())->find('phil:monitoring:watch'));
        self::assertSame(1, $watch->execute([]));
        self::assertStringContainsString('backup: late, last run 2026-11-08T10:00:00+00:00, alerted', $watch->getDisplay());

        $this->client->request('GET', '/_health/ready');
        self::assertSame('{"status":"degraded"}', $this->client->getResponse()->getContent());

        $monitoring = self::getContainer()->get(MonitoringStatus::class);
        self::assertInstanceOf(MonitoringStatus::class, $monitoring);
        self::assertSame(HealthStatus::Degraded, $monitoring->health()->status());
    }

    public function testTheWatchMessageIsHandled(): void
    {
        $this->service(Heartbeats::class, Heartbeats::class)->beat('backup');
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->modify('+2 hours');
        $bus = self::getContainer()->get('test.bus');
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new WatchHeartbeats());

        $store = self::getContainer()->get('test.'.HeartbeatStore::class);
        self::assertInstanceOf(HeartbeatStore::class, $store);
        self::assertTrue($store->isAlerted('backup'));
    }

    public function testWithoutSecurityThePageOfTheQueuesIsNotServed(): void
    {
        self::assertFalse(self::getContainer()->has(QueuesController::class));
    }

    private function kernel(): TestKernel
    {
        $kernel = $this->client->getKernel();
        self::assertInstanceOf(TestKernel::class, $kernel);

        return $kernel;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get('test.'.$id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
