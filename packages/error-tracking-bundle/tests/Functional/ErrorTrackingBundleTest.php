<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use LogicException;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Override;
use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\AlertDestination;
use Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Phil\ErrorTrackingBundle\Infrastructure\Doctrine\ErrorTables;
use Phil\ErrorTrackingBundle\Infrastructure\Monitoring\ErrorStoreCheck;
use Phil\ErrorTrackingBundle\Infrastructure\Notifications\NotificationAlerter;
use Phil\ErrorTrackingBundle\PhilErrorTrackingBundle;
use Phil\ErrorTrackingBundle\Tests\Functional\App\TestKernel;
use Phil\MonitoringBundle\Application\HealthChecker;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use PHPUnit\Framework\Attributes\CoversNothing;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The bundle in a kernel of its own, each request served as a worker would serve it: handled, terminated, then the
 * services reset.
 */
#[CoversNothing]
final class ErrorTrackingBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $schemaManager = $connection->createSchemaManager();

        foreach ([ErrorTables::EVENT, ErrorTables::GROUP] as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $schemaManager->dropTable($table);
            }
        }

        foreach (ErrorTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function testTheExceptionOfARequestIsKeptScrubbedAndGrouped(): void
    {
        self::assertSame(500, $this->serve(Request::create('https://example.com/failing?token=abc'))->getStatusCode());
        self::assertSame(500, $this->serve(Request::create('https://example.com/failing'))->getStatusCode());
        self::assertSame(404, $this->serve(Request::create('https://example.com/missing'))->getStatusCode());

        $reader = $this->reader();
        $groups = $reader->groups(null, 10);
        self::assertCount(1, $groups);
        self::assertSame('RuntimeException: The export of order 12 failed for [email]', $groups[0]->title);
        self::assertSame(2, $groups[0]->timesSeen);
        self::assertSame(GroupStatus::Open, $groups[0]->status);
        self::assertSame('1.0.0', $groups[0]->lastRelease);

        $events = $reader->events($groups[0]->fingerprint, 10);
        self::assertCount(2, $events);
        self::assertSame(Source::Http, $events[1]->source);
        self::assertSame('https://example.com/failing?token=[Filtered]', $events[1]->request?->url);
        self::assertSame('127.0.0.0', $events[1]->request->clientAddress);
        self::assertSame('TestKernel.php', $events[1]->exceptions[0]->frames[0]->file);
        self::assertTrue($events[1]->exceptions[0]->frames[0]->inApp);
    }

    public function testATokenInThePathAndTheAddressesOfTheProxiesAreNeverKept(): void
    {
        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

        try {
            $response = $this->serve(Request::create('https://example.com/reset/abc123?lang=fr', 'GET', [], [], [], [
                'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
                'HTTP_X_REAL_IP' => '203.0.113.9',
                'HTTP_REFERER' => 'https://example.com/reset/abc123?step=2',
                'HTTP_ORIGIN' => 'https://example.com',
            ]));
        } finally {
            Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        }

        self::assertSame(500, $response->getStatusCode());
        $reader = $this->reader();
        $event = $reader->events($reader->groups(null, 10)[0]->fingerprint, 10)[0];
        self::assertSame('https://example.com/reset/[Filtered]?lang=[Filtered]', $event->request?->url);
        self::assertSame('https://example.com/reset/[Filtered]?step=[Filtered]', $event->request->headers['referer']);
        self::assertSame('https://example.com', $event->request->headers['origin']);
        self::assertSame('203.0.113.0', $event->request->clientAddress);
        self::assertArrayNotHasKey('x-forwarded-for', $event->request->headers);
        self::assertArrayNotHasKey('x-real-ip', $event->request->headers);
        self::assertStringNotContainsString('abc123', (string) json_encode($event->toArray()));
        self::assertStringNotContainsString('203.0.113.9', (string) json_encode($event->toArray()));
    }

    public function testAnExceptionTheRedactionOfThePrivacyBundleTurnedIntoAnArrayKeepsItsGroup(): void
    {
        $handler = self::getContainer()->get(PhilErrorTrackingBundle::MONOLOG_HANDLER);
        self::assertInstanceOf(HandlerInterface::class, $handler);
        $logger = new Logger('app', [$handler], [RedactingProcessor::fromConfiguration(['password'], ['/reset/'])]);

        $exception = $this->failure('https://example.com/reset/abc123?password=hunter2');
        $logger->error('Export failed', ['exception' => $exception]);
        $logger->error('Export failed again', ['exception' => $this->failure('https://example.com/reset/def456')]);

        $reader = $this->reader();
        $groups = $reader->groups(null, 10);
        self::assertCount(1, $groups);
        self::assertSame(2, $groups[0]->timesSeen);
        self::assertStringStartsWith('RuntimeException: Could not reach https://example.com/reset/', $groups[0]->title);

        $event = $reader->events($groups[0]->fingerprint, 10)[1];
        self::assertSame(__FILE__.':'.$exception->getLine(), $event->exceptions[0]->frames[0]->label());
        self::assertSame(Source::Log, $event->source);
        self::assertSame([RuntimeException::class, LogicException::class], array_map(static fn (ExceptionRecord $record): string => $record->type, $event->exceptions));
        self::assertStringNotContainsString('hunter2', (string) json_encode($event->toArray()));
        self::assertStringNotContainsString('abc123', (string) json_encode($event->toArray()));

        $safeCapture = self::getContainer()->get(SafeCapture::class);
        self::assertInstanceOf(SafeCapture::class, $safeCapture);
        $captured = $this->failure('https://example.com/other');
        $safeCapture->throwable($captured, new CaptureContext(Source::Http));
        $logger->critical('Uncaught', ['exception' => $captured]);
        self::assertSame(3, $reader->groups(null, 10)[0]->timesSeen);
    }

    public function testABrowserReportIsKept(): void
    {
        $request = Request::create('https://example.com/_errors/browser', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_X_ERROR_TRACKING_TOKEN' => 's3cret',
        ], '{"type":"TypeError","message":"x is undefined","url":"https://example.com/cart","stack":"    at f (https://example.com/app.js:1:2)"}');

        self::assertSame(204, $this->serve($request)->getStatusCode());
        self::assertSame(403, $this->serve(Request::create('https://example.com/_errors/browser', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://example.com'], '{}'))->getStatusCode());

        $groups = $this->reader()->groups(null, 10);
        self::assertSame('TypeError: x is undefined', $groups[0]->title);
        self::assertSame('f (https://example.com/app.js:1)', $groups[0]->culprit);
    }

    public function testANewErrorNotifiesItsRecipientsByItsFingerprint(): void
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        self::assertSame(500, $kernel->handle(Request::create('https://example.com/failing'))->getStatusCode());

        $repository = self::getContainer()->get(NotificationRepository::class);
        self::assertInstanceOf(NotificationRepository::class, $repository);
        $notifications = $repository->latest('ops', 10);
        self::assertCount(1, $notifications);
        self::assertSame(NotificationAlerter::TYPE, $notifications[0]->type);
        self::assertSame($this->reader()->groups(null, 1)[0]->fingerprint, $notifications[0]->subject?->id);
    }

    public function testTheMonitoringChecksTheStore(): void
    {
        $checker = self::getContainer()->get(HealthChecker::class);
        self::assertInstanceOf(HealthChecker::class, $checker);

        self::assertTrue(array_any($checker->report()->results, static fn (CheckResult $result): bool => ErrorStoreCheck::NAME === $result->name && HealthStatus::Up === $result->status()));
    }

    public function testTheBrowsersAreHeldByANamedLimiter(): void
    {
        $limiters = self::getContainer()->get(Limiters::class);
        self::assertInstanceOf(Limiters::class, $limiters);

        self::assertInstanceOf(SymfonyLimiter::class, $limiters->get(PhilErrorTrackingBundle::BROWSER_LIMITER));
    }

    public function testAlertsOnByDefaultBuildWithABusAndNoChatterInTheDegradedState(): void
    {
        $kernel = new TestKernel('chatless', true);
        $kernel->boot();

        self::assertInstanceOf(ErrorTracker::class, $kernel->getContainer()->get(ErrorTracker::class));
        self::assertSame(AlertDestination::None, $kernel->getContainer()->get(AlertDestination::class));

        $tester = new CommandTester(new Application($kernel)->find('phil:error-tracking:status'));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Alerts: degraded. '.AlertDestination::None->describe(), $tester->getDisplay());
        $kernel->shutdown();
    }

    public function testTheStatusOfAnApplicationWhoseAlertsAreNotified(): void
    {
        self::assertSame(AlertDestination::Notification, self::getContainer()->get(AlertDestination::class));

        $tester = new CommandTester(new Application(self::$kernel ?? throw new LogicException('No kernel.'))->find('phil:error-tracking:status'));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Store: answers, 0 group(s) kept.', $tester->getDisplay());
        self::assertStringContainsString('Capture of failed messages: off.', $tester->getDisplay());
    }

    public function testProductionRefusesAPoolLocalToEachServer(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('rate_limit.cache_pool: the store "cache.app" is local to each server');

        new TestKernel('prod', false)->boot();
    }

    public function testProductionOnOneServerAcceptsALocalPoolWhenItSaysSo(): void
    {
        $kernel = new TestKernel('prod', false, singleServer: true);
        $kernel->boot();

        self::assertInstanceOf(ErrorTracker::class, $kernel->getContainer()->get(ErrorTracker::class));
        $kernel->shutdown();
    }

    public function testAlertsTurnedOffBuildWithoutTheWarningOfTheDegradedState(): void
    {
        $kernel = new TestKernel('quiet', true);
        $kernel->boot();

        self::assertSame(AlertDestination::Disabled, $kernel->getContainer()->get(AlertDestination::class));
        $tester = new CommandTester(new Application($kernel)->find('phil:error-tracking:status'));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Alerts: '.AlertDestination::Disabled->describe(), $tester->getDisplay());
        self::assertStringNotContainsString('degraded', $tester->getDisplay());
        $kernel->shutdown();
    }

    public function testTheLogHandlerAndThePurge(): void
    {
        $handler = self::getContainer()->get(PhilErrorTrackingBundle::MONOLOG_HANDLER);
        self::assertInstanceOf(HandlerInterface::class, $handler);

        $application = new Application(self::$kernel ?? throw new LogicException('No kernel.'));
        $tester = new CommandTester($application->find('phil:error-tracking:purge'));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('0 event(s) and group(s) removed.', $tester->getDisplay());
    }

    private function failure(string $url): RuntimeException
    {
        return new RuntimeException('Could not reach '.$url, 0, new LogicException('Refused'));
    }

    private function serve(Request $request): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $response = $kernel->handle($request);

        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }

        $resetter = self::getContainer()->get('services_resetter');
        self::assertInstanceOf(ResetInterface::class, $resetter);
        $resetter->reset();

        return $response;
    }

    private function reader(): ErrorGroupReader
    {
        $reader = self::getContainer()->get(ErrorGroupReader::class);
        self::assertInstanceOf(ErrorGroupReader::class, $reader);

        return $reader;
    }
}
