<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit;

use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Error;
use LogicException;
use Phil\ErrorTracking\Contract\ErrorStore;
use Phil\ErrorTracking\ErrorTracker;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\Exception\InvalidOption;
use Phil\ErrorTracking\Fingerprinter;
use Phil\ErrorTracking\Sampler;
use Phil\ErrorTracking\Scrubber;
use Phil\ErrorTrackingBundle\Application\AlertDestination;
use Phil\ErrorTrackingBundle\Application\BrowserErrorParser;
use Phil\ErrorTrackingBundle\Application\BrowserErrorReceiver;
use Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader;
use Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup;
use Phil\ErrorTrackingBundle\Application\NewGroupsThreshold;
use Phil\ErrorTrackingBundle\Application\SafeCapture;
use Phil\ErrorTrackingBundle\Infrastructure\Console\ConsoleErrorListener;
use Phil\ErrorTrackingBundle\Infrastructure\Console\PurgeCommand;
use Phil\ErrorTrackingBundle\Infrastructure\Console\StatusCommand;
use Phil\ErrorTrackingBundle\Infrastructure\DependencyInjection\AlertsPass;
use Phil\ErrorTrackingBundle\Infrastructure\Doctrine\DbalErrorStore;
use Phil\ErrorTrackingBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\ErrorTrackingBundle\Infrastructure\Http\AdminController;
use Phil\ErrorTrackingBundle\Infrastructure\Http\BrowserErrorController;
use Phil\ErrorTrackingBundle\Infrastructure\Http\ExceptionListener;
use Phil\ErrorTrackingBundle\Infrastructure\Http\RequestSummaries;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\AlertErrorGroupHandler;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\MessengerAlerter;
use Phil\ErrorTrackingBundle\Infrastructure\Messenger\WorkerFailureListener;
use Phil\ErrorTrackingBundle\Infrastructure\Monitoring\ErrorStoreCheck;
use Phil\ErrorTrackingBundle\Infrastructure\Monolog\ErrorTrackingHandler;
use Phil\ErrorTrackingBundle\Infrastructure\Notifications\ErrorGroupSubjects;
use Phil\ErrorTrackingBundle\Infrastructure\Notifications\NotificationAlerter;
use Phil\ErrorTrackingBundle\Infrastructure\Notifier\ChatterAlerter;
use Phil\ErrorTrackingBundle\Infrastructure\RateLimit\LimiterCaptureThrottle;
use Phil\ErrorTrackingBundle\Infrastructure\RateLimiter\RateLimiterThrottle;
use Phil\ErrorTrackingBundle\PhilErrorTrackingBundle;
use Phil\IpAnonymizer\Truncation;
use Phil\MonitoringBundle\PhilMonitoringBundle;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Phil\RateLimit\RateLimitGuard;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * The wiring the bundle declares, read on the `ContainerBuilder`.
 */
#[CoversClass(PhilErrorTrackingBundle::class)]
final class PhilErrorTrackingBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilErrorTracking(): void
    {
        self::assertSame('phil_error_tracking', new PhilErrorTrackingBundle()->getContainerExtension()?->getAlias());
        self::assertSame('phil_error_tracking.monolog_handler', PhilErrorTrackingBundle::MONOLOG_HANDLER);
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([24, 48], $container->getDefinition('phil_error_tracking.ip_anonymizer')->getArguments());
        self::assertSame(Truncation::class, $container->getDefinition('phil_error_tracking.ip_anonymizer')->getClass());
        self::assertEquals(['%kernel.project_dir%', new Reference('clock'), null, '%kernel.environment%', 50, 10], $container->getDefinition(EventFactory::class)->getArguments());
        self::assertTrue($container->getDefinition(EventFactory::class)->isPublic());
        self::assertSame([[], []], $container->getDefinition(Scrubber::class)->getArguments());
        self::assertTrue($container->hasDefinition(Fingerprinter::class));
        self::assertSame([1.0], $container->getDefinition(Sampler::class)->getArguments());
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), 50], $container->getDefinition(DbalErrorStore::class)->getArguments());
        self::assertSame(DbalErrorStore::class, (string) $container->getAlias(ErrorStore::class));
        self::assertTrue($container->getAlias(ErrorStore::class)->isPublic());
        self::assertSame(DbalErrorStore::class, (string) $container->getAlias(ErrorGroupReader::class));
        self::assertTrue($container->getAlias(ErrorGroupReader::class)->isPublic());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(SchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(SchemaListener::class)->getTags());

        $tracker = $container->getDefinition(ErrorTracker::class);
        self::assertEquals([
            new Reference(EventFactory::class),
            new Reference(ErrorStore::class),
            new Reference(Scrubber::class),
            new Reference(Fingerprinter::class),
            new Reference(Sampler::class),
            new Reference('phil_error_tracking.group_throttle'),
            new Reference(MessengerAlerter::class),
        ], $tracker->getArguments());
        self::assertTrue($tracker->isPublic());
        self::assertSame(RateLimiterThrottle::class, $container->getDefinition('phil_error_tracking.group_throttle')->getClass());
        self::assertEquals([new Reference('phil_error_tracking.group_throttle.limiter')], $container->getDefinition('phil_error_tracking.group_throttle')->getArguments());
        self::assertSame(RateLimiterFactory::class, $container->getDefinition('phil_error_tracking.group_throttle.limiter')->getClass());
        self::assertEquals([
            ['id' => 'phil_error_tracking_group', 'policy' => 'sliding_window', 'limit' => 30, 'interval' => '1 minute'],
            new Reference('phil_error_tracking.group_throttle.storage'),
        ], $container->getDefinition('phil_error_tracking.group_throttle.limiter')->getArguments());
        self::assertSame(CacheStorage::class, $container->getDefinition('phil_error_tracking.group_throttle.storage')->getClass());
        self::assertEquals([new Reference('cache.app')], $container->getDefinition('phil_error_tracking.group_throttle.storage')->getArguments());

        $capture = $container->getDefinition(SafeCapture::class);
        self::assertEquals([new Reference(ErrorTracker::class), [], new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $capture->getArguments());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $capture->getTags());
        self::assertTrue($capture->isPublic());
        self::assertEquals([new Reference('phil_error_tracking.ip_anonymizer')], $container->getDefinition(RequestSummaries::class)->getArguments());

        $listener = $container->getDefinition(ExceptionListener::class);
        self::assertEquals([new Reference(SafeCapture::class), new Reference(RequestSummaries::class), false], $listener->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => 'kernel.exception', 'method' => 'onKernelException', 'priority' => 128]]], $listener->getTags());
        $worker = $container->getDefinition(WorkerFailureListener::class);
        self::assertEquals([new Reference(SafeCapture::class), false], $worker->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => WorkerMessageFailedEvent::class, 'method' => 'onMessageFailed']]], $worker->getTags());
        $console = $container->getDefinition(ConsoleErrorListener::class);
        self::assertEquals([new Reference(SafeCapture::class)], $console->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => 'console.error', 'method' => 'onConsoleError']]], $console->getTags());
        $handler = $container->getDefinition(PhilErrorTrackingBundle::MONOLOG_HANDLER);
        self::assertSame(ErrorTrackingHandler::class, $handler->getClass());
        self::assertEquals([new Reference(SafeCapture::class), new Reference(EventFactory::class), 'error'], $handler->getArguments());
        self::assertTrue($handler->isPublic());

        $purge = $container->getDefinition(PurgeCommand::class);
        self::assertEquals([new Reference(ErrorGroupReader::class), new Reference('clock'), 30], $purge->getArguments());
        self::assertSame(['console.command' => [[]]], $purge->getTags());
        $status = $container->getDefinition(StatusCommand::class);
        self::assertEquals([
            new Reference(ErrorGroupReader::class),
            new Reference(AlertDestination::class),
            ['requests' => true, 'failed messages' => true, 'commands' => true, 'log records' => true, "browsers' reports" => false],
        ], $status->getArguments());
        self::assertSame(['console.command' => [[]]], $status->getTags());

        $destination = $container->getDefinition(AlertDestination::class);
        self::assertSame(AlertDestination::class, $destination->getClass());
        self::assertSame([AlertDestination::class, 'from'], $destination->getFactory());
        self::assertSame([AlertDestination::Chat->value], $destination->getArguments(), 'the alerts fall back on the chat');
        self::assertTrue($destination->isPublic());

        self::assertFalse($container->hasDefinition(AdminController::class), 'the administration is enabled explicitly');
        $admin = $this->load(['admin' => true])->getDefinition(AdminController::class);
        self::assertEquals([
            new Reference(ErrorGroupReader::class),
            new Reference(ErrorStore::class),
            new Reference('security.authorization_checker'),
            new Reference('security.csrf.token_manager'),
            new Reference('twig'),
            new Reference('router'),
            new Reference(AlertDestination::class),
            'ROLE_ADMIN',
            '@PhilErrorTracking/layout.html.twig',
            50,
            10,
        ], $admin->getArguments());
        self::assertTrue($admin->isPublic());
        self::assertSame(['controller.service_arguments' => [[]]], $admin->getTags(), 'no back office without EasyAdminBundle');
        self::assertSame(
            ['controller.service_arguments' => [[]], EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER => [[]]],
            $this->load(['admin' => true], ['EasyAdminBundle' => EasyAdminBundle::class])->getDefinition(AdminController::class)->getTags(),
            'the pages are grafted on the dashboards',
        );
        self::assertFalse($this->load(['admin' => false], ['EasyAdminBundle' => EasyAdminBundle::class])->hasDefinition(AdminController::class));

        self::assertTrue($container->hasDefinition(ChatterAlerter::class), 'the alerts are on by default');
        self::assertFalse($container->hasDefinition(BrowserErrorReceiver::class));
        self::assertFalse($container->hasDefinition(BrowserErrorController::class));
    }

    public function testEverySettingIsPassedOn(): void
    {
        $container = $this->load([
            'release' => '1.4.0',
            'environment' => 'staging',
            'project_dir' => '/srv/app',
            'sample_rate' => 0.5,
            'max_frames' => 20,
            'max_chain' => 3,
            'scrub_fields' => ['birth_date'],
            'token_path_prefixes' => ['/reset-password/reset/'],
            'ignored_exceptions' => [LogicException::class],
            'client_address' => ['ipv4_prefix' => 16, 'ipv6_prefix' => 32],
            'rate_limit' => ['limit' => 5, 'interval' => '10 seconds', 'cache_pool' => 'cache.errors'],
            'capture' => ['http' => ['client_errors' => true], 'messenger' => ['retries' => true], 'monolog_level' => 'critical'],
            'storage' => ['connection' => 'logs', 'events_per_group' => 7, 'retention' => 90],
            'admin' => ['role' => 'ROLE_DEV', 'layout' => 'admin.html.twig', 'page_size' => 25, 'events_shown' => 3],
            'clock' => 'app.clock',
        ]);

        self::assertSame([16, 32], $container->getDefinition('phil_error_tracking.ip_anonymizer')->getArguments());
        self::assertEquals(['/srv/app', new Reference('app.clock'), '1.4.0', 'staging', 20, 3], $container->getDefinition(EventFactory::class)->getArguments());
        self::assertSame([['birth_date'], ['/reset-password/reset/']], $container->getDefinition(Scrubber::class)->getArguments());
        self::assertSame([0.5], $container->getDefinition(Sampler::class)->getArguments());
        self::assertEquals([new Reference('doctrine.dbal.logs_connection'), 7], $container->getDefinition(DbalErrorStore::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'logs']]], $container->getDefinition(SchemaListener::class)->getTags());
        self::assertEquals([
            ['id' => 'phil_error_tracking_group', 'policy' => 'sliding_window', 'limit' => 5, 'interval' => '10 seconds'],
            new Reference('phil_error_tracking.group_throttle.storage'),
        ], $container->getDefinition('phil_error_tracking.group_throttle.limiter')->getArguments());
        self::assertEquals([new Reference('cache.errors')], $container->getDefinition('phil_error_tracking.group_throttle.storage')->getArguments());
        self::assertSame([LogicException::class], $container->getDefinition(SafeCapture::class)->getArgument(1));
        self::assertTrue($container->getDefinition(ExceptionListener::class)->getArgument(2));
        self::assertTrue($container->getDefinition(WorkerFailureListener::class)->getArgument(1));
        self::assertSame('critical', $container->getDefinition(PhilErrorTrackingBundle::MONOLOG_HANDLER)->getArgument(2));
        self::assertEquals([new Reference(ErrorGroupReader::class), new Reference('app.clock'), 90], $container->getDefinition(PurgeCommand::class)->getArguments());
        self::assertSame(['ROLE_DEV', 'admin.html.twig', 25, 3], \array_slice($container->getDefinition(AdminController::class)->getArguments(), 7));
    }

    public function testCapturePointsAndThrottleTurnedOff(): void
    {
        $container = $this->load(['rate_limit' => false, 'capture' => ['http' => false, 'messenger' => false, 'console' => false]]);

        self::assertNull($container->getDefinition(ErrorTracker::class)->getArgument(5));
        self::assertFalse($container->hasDefinition('phil_error_tracking.group_throttle'));
        self::assertFalse($container->hasDefinition(ExceptionListener::class));
        self::assertFalse($container->hasDefinition(WorkerFailureListener::class));
        self::assertFalse($container->hasDefinition(ConsoleErrorListener::class));
        self::assertTrue($container->hasDefinition(PhilErrorTrackingBundle::MONOLOG_HANDLER));
        self::assertSame(
            ['requests' => false, 'failed messages' => false, 'commands' => false, 'log records' => true, "browsers' reports" => false],
            $container->getDefinition(StatusCommand::class)->getArgument(2),
        );
        self::assertSame(
            ['requests' => true, 'failed messages' => true, 'commands' => true, 'log records' => true, "browsers' reports" => true],
            $this->load(['browser' => true])->getDefinition(StatusCommand::class)->getArgument(2),
        );
        self::assertFalse($this->load(['admin' => false])->hasDefinition(AdminController::class));
    }

    public function testTheAlertsTurnedOff(): void
    {
        $container = $this->load(['alerts' => false]);

        self::assertNull($container->getDefinition(ErrorTracker::class)->getArgument(6));
        self::assertFalse($container->hasDefinition(ChatterAlerter::class));
        self::assertSame([AlertDestination::Disabled->value], $container->getDefinition(AlertDestination::class)->getArguments());
    }

    public function testTheBundleAddsItsPasses(): void
    {
        $container = new ContainerBuilder();
        new PhilErrorTrackingBundle()->build($container);
        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();

        self::assertTrue(array_any($passes, static fn (object $pass): bool => $pass instanceof RateLimitGuard));
        self::assertTrue(array_any($passes, static fn (object $pass): bool => $pass instanceof AlertsPass));
    }

    public function testTheHealthCheck(): void
    {
        $monitoring = ['PhilMonitoringBundle' => PhilMonitoringBundle::class];
        $definition = $this->load([], $monitoring)->getDefinition(ErrorStoreCheck::class);

        self::assertEquals([new Reference(ErrorGroupReader::class), new Reference('clock'), 20, 900], $definition->getArguments());
        self::assertTrue($definition->isAutoconfigured());

        self::assertSame([5, 60], \array_slice($this->load(['health_check' => ['new_groups' => 5, 'window' => 60]], $monitoring)->getDefinition(ErrorStoreCheck::class)->getArguments(), 2));
        self::assertSame(NewGroupsThreshold::MAX, $this->load(['health_check' => ['new_groups' => NewGroupsThreshold::MAX]], $monitoring)->getDefinition(ErrorStoreCheck::class)->getArgument(2));
        self::assertFalse($this->load(['health_check' => false], $monitoring)->hasDefinition(ErrorStoreCheck::class));
        self::assertFalse($this->load([])->hasDefinition(ErrorStoreCheck::class), 'without phil/monitoring-bundle enabled');
    }

    public function testTheThresholdOfNewGroupsIsBounded(): void
    {
        self::assertSame(NewGroupsThreshold::MAX, ErrorStoreCheck::MAX_NEW_GROUPS);
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['health_check' => ['new_groups' => NewGroupsThreshold::MAX + 1]]);
    }

    public function testTheAlertsThroughNotifications(): void
    {
        $container = $this->load(['alerts' => ['recipients' => ['ops', 'dev']], 'admin' => true], ['PhilNotificationsBundle' => PhilNotificationsBundle::class]);

        self::assertEquals([new Reference(Notifications::class), ['ops', 'dev'], null], $container->getDefinition(NotificationAlerter::class)->getArguments());
        self::assertSame([AlertDestination::Notification->value], $container->getDefinition(AlertDestination::class)->getArguments());
        self::assertEquals(new Reference(NotificationAlerter::class), $container->getDefinition(ErrorTracker::class)->getArgument(6));
        self::assertFalse($container->hasDefinition(ChatterAlerter::class));
        self::assertEquals([new Reference(ErrorStore::class), new Reference('router')], $container->getDefinition(ErrorGroupSubjects::class)->getArguments());
        self::assertTrue($container->getDefinition(ErrorGroupSubjects::class)->isAutoconfigured());
    }

    public function testTheAlertsToAnAudienceWithoutAdministration(): void
    {
        $container = $this->load(['alerts' => ['audience' => 'operators']], ['PhilNotificationsBundle' => PhilNotificationsBundle::class]);

        self::assertEquals([new Reference(Notifications::class), [], 'operators'], $container->getDefinition(NotificationAlerter::class)->getArguments());
        self::assertSame([AlertDestination::Notification->value], $container->getDefinition(AlertDestination::class)->getArguments());
        self::assertEquals([new Reference(ErrorStore::class), null], $container->getDefinition(ErrorGroupSubjects::class)->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, class-string>, string}>
     */
    public static function refusedNotifications(): iterable
    {
        yield 'both' => [['recipients' => ['ops'], 'audience' => 'operators'], ['PhilNotificationsBundle' => PhilNotificationsBundle::class], 'name recipients or an audience, not both'];
        yield 'not registered' => [['recipients' => ['ops']], [], 'install it and register PhilNotificationsBundle'];
        yield 'a recipient that is an address' => [['recipients' => ['jane@example.com']], ['PhilNotificationsBundle' => PhilNotificationsBundle::class], 'is not an identifier'];
    }

    /**
     * @param array<string, mixed>        $alerts
     * @param array<string, class-string> $bundles
     */
    #[DataProvider('refusedNotifications')]
    public function testNotificationsTheBuildRefuses(array $alerts, array $bundles, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load(['alerts' => $alerts], $bundles);
    }

    public function testAnInvalidRecipientKeepsItsCause(): void
    {
        try {
            $this->load(['alerts' => ['recipients' => ['jane@example.com']]], ['PhilNotificationsBundle' => PhilNotificationsBundle::class]);
            self::fail('The recipient is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringStartsWith('phil_error_tracking.alerts: ', $invalidConfigurationException->getMessage());
            self::assertInstanceOf(InvalidNotification::class, $invalidConfigurationException->getPrevious());
        }
    }

    public function testTheNotificationTypeIsDeclared(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new PhilNotificationsBundle()->getContainerExtension() ?? throw new RuntimeException('No extension.'));

        $bundle = new PhilErrorTrackingBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['types' => ['phil_error_tracking.group_alert' => ['channels' => ['site', 'email'], 'collapse' => true, 'digest' => false, 'importance' => 'high']]]], $container->getExtensionConfig('phil_notifications'));
    }

    public function testNoTypeWithoutTheNotificationsBundle(): void
    {
        $container = new ContainerBuilder();
        $extension = new PhilErrorTrackingBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('phil_notifications'));
        self::assertSame([], $container->getExtensionConfig('asset_mapper'));
    }

    public function testTheStimulusControllerIsMappedWhenAssetMapperIsThere(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new AssetMapperBundle()->getContainerExtension() ?? throw new LogicException('AssetMapper has an extension.'));

        $extension = new PhilErrorTrackingBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        $configs = $container->getExtensionConfig('asset_mapper');
        self::assertCount(1, $configs);
        self::assertIsArray($configs[0]);
        $paths = $configs[0]['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertSame(['@phil/error-tracking-bundle'], array_values($paths));
        self::assertIsString(array_key_first($paths));
        self::assertFileExists(array_key_first($paths).'/capture_controller.js');

        $package = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/assets/package.json'), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($package);
        self::assertSame('@phil/error-tracking-bundle', $package['name'] ?? null, 'the name AssetMapper maps the controllers under');
        $symfony = $package['symfony'] ?? null;
        self::assertIsArray($symfony);
        $controllers = $symfony['controllers'] ?? null;
        self::assertIsArray($controllers);
        self::assertSame(['main' => 'src/capture_controller.js', 'webpackMode' => 'eager', 'fetch' => 'eager', 'enabled' => true], $controllers['capture'] ?? null);
    }

    public function testTheBrowsersFallBackOnTheRateLimiterComponent(): void
    {
        $container = $this->load(['browser' => true]);

        self::assertSame(RateLimiterThrottle::class, $container->getDefinition('phil_error_tracking.browser_throttle')->getClass());
        self::assertSame([
            'phil_error_tracking.rate_limit.cache_pool' => ['store' => 'cache.app', 'switch' => 'enabled'],
            'phil_error_tracking.browser.rate_limit.cache_pool' => ['store' => 'cache.app', 'switch' => 'enabled'],
        ], RateLimitGuard::watched($container), 'each configuration is checked, even on one pool');
    }

    public function testTheBrowsersGoThroughANamedLimiterOfTheRateLimitBundle(): void
    {
        $container = $this->load(['browser' => true], ['PhilRateLimitBundle' => PhilRateLimitBundle::class]);

        self::assertEquals(new Reference('phil_error_tracking.browser_throttle'), $container->getDefinition(BrowserErrorReceiver::class)->getArgument(8));
        $throttle = $container->getDefinition('phil_error_tracking.browser_throttle');
        self::assertSame(LimiterCaptureThrottle::class, $throttle->getClass());
        self::assertEquals([new Reference('phil_error_tracking.browser_throttle.limit'), new Reference('request_stack')], $throttle->getArguments());
        $limiter = $container->getDefinition('phil_error_tracking.browser_throttle.limit');
        self::assertSame(SymfonyLimiter::class, $limiter->getClass());
        self::assertEquals([new Reference('phil_error_tracking.browser_throttle.limiter')], $limiter->getArguments());
        self::assertSame([['name' => 'phil_error_tracking.browser']], $limiter->getTag('phil_rate_limit.limiter'));
        self::assertSame(RateLimiterThrottle::class, $container->getDefinition('phil_error_tracking.group_throttle')->getClass(), 'the groups have no request to count');
    }

    public function testTheGroupThrottleAndItsPoolAreWatched(): void
    {
        self::assertSame(['phil_error_tracking.rate_limit.cache_pool' => ['store' => 'cache.errors', 'switch' => 'enabled']], RateLimitGuard::watched($this->load(['rate_limit' => ['cache_pool' => 'cache.errors'], 'alerts' => false])));
    }

    public function testQueuedAlerts(): void
    {
        $container = $this->load(['alerts' => ['transport' => 'discord']]);

        self::assertEquals([new Reference('chatter'), 'discord', '%kernel.environment%'], $container->getDefinition(ChatterAlerter::class)->getArguments());
        self::assertEquals([new Reference('messenger.default_bus')], $container->getDefinition(MessengerAlerter::class)->getArguments());
        $handler = $container->getDefinition(AlertErrorGroupHandler::class);
        self::assertEquals([new Reference(ErrorStore::class), new Reference(ChatterAlerter::class)], $handler->getArguments());
        self::assertSame(['messenger.message_handler' => [['handles' => AlertErrorGroup::class]]], $handler->getTags());
        self::assertEquals(new Reference(MessengerAlerter::class), $container->getDefinition(ErrorTracker::class)->getArgument(6));
    }

    public function testAlertsSentAtOnce(): void
    {
        $container = $this->load(['environment' => 'prod', 'alerts' => ['async' => false]]);

        self::assertEquals([new Reference('chatter'), null, 'prod'], $container->getDefinition(ChatterAlerter::class)->getArguments());
        self::assertFalse($container->hasDefinition(MessengerAlerter::class));
        self::assertFalse($container->hasDefinition(AlertErrorGroupHandler::class));
        self::assertEquals(new Reference(ChatterAlerter::class), $container->getDefinition(ErrorTracker::class)->getArgument(6));
    }

    public function testTheBrowserEndpoint(): void
    {
        $container = $this->load(['browser' => ['origins' => ['https://Example.com', 'http://localhost:8000'], 'token' => 's3cret', 'max_bytes' => 4096, 'max_frames' => 20, 'rate_limit' => ['limit' => 3, 'interval' => '1 hour', 'cache_pool' => 'cache.browser']]]);

        self::assertSame([20], $container->getDefinition(BrowserErrorParser::class)->getArguments());
        self::assertEquals([
            new Reference(BrowserErrorParser::class),
            new Reference(EventFactory::class),
            new Reference(SafeCapture::class),
            new Reference('phil_error_tracking.ip_anonymizer'),
            '%kernel.secret%',
            4096,
            ['https://example.com', 'http://localhost:8000'],
            's3cret',
            new Reference('phil_error_tracking.browser_throttle'),
        ], $container->getDefinition(BrowserErrorReceiver::class)->getArguments());
        self::assertEquals([
            ['id' => 'phil_error_tracking_browser', 'policy' => 'sliding_window', 'limit' => 3, 'interval' => '1 hour'],
            new Reference('phil_error_tracking.browser_throttle.storage'),
        ], $container->getDefinition('phil_error_tracking.browser_throttle.limiter')->getArguments());
        self::assertEquals([new Reference('cache.browser')], $container->getDefinition('phil_error_tracking.browser_throttle.storage')->getArguments());
        $controller = $container->getDefinition(BrowserErrorController::class);
        self::assertEquals([new Reference(BrowserErrorReceiver::class)], $controller->getArguments());
        self::assertTrue($controller->isPublic());
        self::assertSame(['controller.service_arguments' => [[]]], $controller->getTags());
    }

    public function testTheBrowserEndpointByDefault(): void
    {
        $container = $this->load(['browser' => ['rate_limit' => false]]);

        self::assertSame([50], $container->getDefinition(BrowserErrorParser::class)->getArguments());
        self::assertSame([16_384, [], null, null], \array_slice($container->getDefinition(BrowserErrorReceiver::class)->getArguments(), 5));
        self::assertEquals([
            ['id' => 'phil_error_tracking_browser', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '1 minute'],
            new Reference('phil_error_tracking.browser_throttle.storage'),
        ], $this->load(['browser' => true])->getDefinition('phil_error_tracking.browser_throttle.limiter')->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'an origin with a path' => [['browser' => ['origins' => ['https://example.com/']]], 'phil_error_tracking.browser.origins: "https://example.com/" is not an origin such as https://example.com.'];
        yield 'an origin without scheme' => [['browser' => ['origins' => ['example.com']]], 'phil_error_tracking.browser.origins: "example.com"'];
        yield 'an origin then a new line' => [['browser' => ['origins' => ["https://example.com\n"]]], 'phil_error_tracking.browser.origins:'];
        yield 'an origin with a prefix' => [['browser' => ['origins' => ['xhttps://example.com']]], 'phil_error_tracking.browser.origins:'];
        yield 'an ignored class that is not a throwable' => [['ignored_exceptions' => [stdClass::class]], 'phil_error_tracking.ignored_exceptions: "stdClass" is not a throwable class.'];
        yield 'an ignored class that does not exist' => [['ignored_exceptions' => ['App\Missing']], 'phil_error_tracking.ignored_exceptions: "App\Missing"'];
        yield 'a clock of another type' => [['clock' => true], 'phil_error_tracking: clock must be a string.'];
        yield 'a project directory of another type' => [['project_dir' => true], 'phil_error_tracking: project_dir must be a string.'];
        yield 'an empty project directory' => [['project_dir' => ''], 'phil_error_tracking: project_dir must be a string.'];
        yield 'a sample rate above one' => [['sample_rate' => 1.5], 'sample_rate'];
        yield 'a token path prefix without its slashes' => [['token_path_prefixes' => ['reset']], 'phil_error_tracking.token_path_prefixes: "reset" is not a token path prefix: expected one or more path segments between slashes, such as "/reset-password/reset/".'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function testAnInvalidConfigurationFailsTheBuild(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function bounds(): iterable
    {
        $bounds = [
            'sample_rate' => [[], 'sample_rate', 0.0, 1.0, -0.01, 1.01],
            'max_frames' => [[], 'max_frames', 1, 500, 0, 501],
            'max_chain' => [[], 'max_chain', 1, 50, 0, 51],
            'ipv4_prefix' => [['client_address'], 'ipv4_prefix', 0, 32, -1, 33],
            'ipv6_prefix' => [['client_address'], 'ipv6_prefix', 0, 128, -1, 129],
            'rate limit' => [['rate_limit'], 'limit', 1, null, 0, null],
            'events_per_group' => [['storage'], 'events_per_group', 1, 10_000, 0, 10_001],
            'retention' => [['storage'], 'retention', 0, null, -1, null],
            'page_size' => [['admin'], 'page_size', 1, 500, 0, 501],
            'new_groups' => [['health_check'], 'new_groups', 0, 200, -1, 201],
            'window' => [['health_check'], 'window', 1, 86_400, 0, 86_401],
            'events_shown' => [['admin'], 'events_shown', 1, 500, 0, 501],
            'browser max_bytes' => [['browser'], 'max_bytes', 512, 262_144, 511, 262_145],
            'browser max_frames' => [['browser'], 'max_frames', 1, 200, 0, 201],
            'browser rate limit' => [['browser', 'rate_limit'], 'limit', 1, null, 0, null],
        ];

        foreach ($bounds as $name => [$path, $key, $low, $high, $below, $above]) {
            $nest = static function (mixed $value) use ($path, $key): array {
                $config = [$key => $value];

                foreach (array_reverse($path) as $section) {
                    $config = [$section => $config];
                }

                return $config;
            };

            yield $name.' at its lowest' => [$nest($low), $nest($below)];

            if (null !== $high) {
                yield $name.' at its highest' => [$nest($high), $nest($above)];
            }
        }
    }

    /**
     * @param array<string, mixed> $bound
     * @param array<string, mixed> $outside
     */
    #[DataProvider('bounds')]
    public function testABoundIsAcceptedAndWhatLiesPastItRefused(array $bound, array $outside): void
    {
        self::assertTrue($this->load($bound)->hasDefinition(ErrorTracker::class));

        $this->expectException(InvalidConfigurationException::class);

        $this->load($outside);
    }

    public function testAMalformedTokenPathPrefixKeepsItsCause(): void
    {
        try {
            $this->load(['token_path_prefixes' => ['/p']]);
            self::fail('The build accepted a malformed prefix.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertInstanceOf(InvalidOption::class, $invalidConfigurationException->getPrevious());
            self::assertSame(0, $invalidConfigurationException->getCode());
        }
    }

    public function testOnlyTextsAreKeptOfAList(): void
    {
        $container = $this->load(['scrub_fields' => ['a', 3, 'b'], 'token_path_prefixes' => [4, '/p/'], 'browser' => ['origins' => [8, 'https://a.example']]]);

        self::assertSame([['a', 'b'], ['/p/']], $container->getDefinition(Scrubber::class)->getArguments());
        self::assertSame(['https://a.example'], $container->getDefinition(BrowserErrorReceiver::class)->getArgument(6));
    }

    public function testAnIgnoredThrowableClassIsKept(): void
    {
        self::assertSame([RuntimeException::class, Error::class], $this->load(['ignored_exceptions' => [RuntimeException::class, Error::class]])->getDefinition(SafeCapture::class)->getArgument(1));
        self::assertSame(['https://a.example:8443'], $this->load(['browser' => ['origins' => ['https://a.example:8443']]])->getDefinition(BrowserErrorReceiver::class)->getArgument(6));
        self::assertSame(['https://upper.example'], $this->load(['browser' => ['origins' => ['HTTPS://UPPER.example']]])->getDefinition(BrowserErrorReceiver::class)->getArgument(6), 'the scheme is read in any case');
    }

    /**
     * @param array<string, mixed>        $config
     * @param array<string, class-string> $bundles
     */
    private function load(array $config, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);

        $extension = new PhilErrorTrackingBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }

    public function testTheHandlersAreRemovedBeforeMessengerWiresThem(): void
    {
        $container = new ContainerBuilder();
        new MessengerBundle()->build($container);
        new PhilErrorTrackingBundle()->build($container);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $messenger = array_search(MessengerPass::class, $passes, true);
        self::assertIsInt($messenger);

        $removal = array_search(AlertsPass::class, $passes, true);
        self::assertIsInt($removal);
        self::assertLessThan($messenger, $removal);
    }

    public function testThePassesRunAtPriorityOneNotHigher(): void
    {
        $container = new ContainerBuilder();
        new PhilErrorTrackingBundle()->build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, 2);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $marker = array_search(true, array_map(static fn (string $class): bool => str_contains($class, '@anonymous'), $passes), true);
        self::assertIsInt($marker);

        $removal = array_search(AlertsPass::class, $passes, true);
        self::assertIsInt($removal);
        self::assertGreaterThan($marker, $removal, 'AlertsPass must run at priority 1, after a pass of priority 2.');
    }
}
