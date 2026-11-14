<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit;

use Phil\NotificationsBundle\Application\CheckedCurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\AudienceProvider;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\DigestSender;
use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Phil\NotificationsBundle\Application\Contract\RemoteNotificationReceiver;
use Phil\NotificationsBundle\Application\Contract\SubjectResolver;
use Phil\NotificationsBundle\Application\Contract\ToastPublisher;
use Phil\NotificationsBundle\Application\Digests;
use Phil\NotificationsBundle\Application\ForwardingRemoteReceiver;
use Phil\NotificationsBundle\Application\Handler\DigestHandler;
use Phil\NotificationsBundle\Application\Handler\PurgeNotificationsHandler;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Message\PurgeNotifications;
use Phil\NotificationsBundle\Application\Message\SendDigest;
use Phil\NotificationsBundle\Application\Message\SendDigests;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\Preferences\ConfiguredPreferences;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Preferences\QuietHours;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\NotificationsBundle\Application\Retention;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Console\PurgeCommand;
use Phil\NotificationsBundle\Infrastructure\DependencyInjection\NotificationsPass;
use Phil\NotificationsBundle\Infrastructure\DependencyInjection\NotificationsServices;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPreferences;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationSchemaListener;
use Phil\NotificationsBundle\Infrastructure\Http\CentreController;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Http\FlashToasts;
use Phil\NotificationsBundle\Infrastructure\Http\NullToasts;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerDigestSender;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Mercure\MercureToasts;
use Phil\NotificationsBundle\Infrastructure\Notifier\EmptyRecipientDirectory;
use Phil\NotificationsBundle\Infrastructure\Notifier\PlainTranslator;
use Phil\NotificationsBundle\Infrastructure\Remote\RemoteNotificationController;
use Phil\NotificationsBundle\Infrastructure\Scheduler\NotificationSchedule;
use Phil\NotificationsBundle\Infrastructure\Security\NoCurrentRecipient;
use Phil\NotificationsBundle\Infrastructure\Twig\BellComponent;
use Phil\NotificationsBundle\Infrastructure\Twig\CentreView;
use Phil\NotificationsBundle\Infrastructure\Twig\ListComponent;
use Phil\NotificationsBundle\Infrastructure\Twig\ToastsComponent;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Phil\StandardWebhooks\ProcessedWebhookRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

#[CoversClass(PhilNotificationsBundle::class)]
#[CoversClass(NotificationsServices::class)]
#[CoversClass(NotificationsPass::class)]
final class PhilNotificationsBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_notifications', $this->extension()->getAlias());
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame(DbalNotificationRepository::class, (string) $container->getAlias(NotificationRepository::class));
        self::assertTrue($container->getAlias(NotificationRepository::class)->isPublic());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalNotificationRepository::class)->getArguments());
        self::assertSame(ConfiguredPreferences::class, (string) $container->getAlias(NotificationPreferences::class));
        self::assertTrue($container->getAlias(NotificationPreferences::class)->isPublic());
        self::assertSame(EmptyRecipientDirectory::class, (string) $container->getAlias(RecipientDirectory::class));
        self::assertTrue($container->hasDefinition(EmptyRecipientDirectory::class));
        self::assertEquals([new Reference(NotificationRepository::class), new Reference('clock'), 90, 365, 1000, 100], $container->getDefinition(Retention::class)->getArguments());
        self::assertSame(CheckedCurrentRecipient::class, (string) $container->getAlias(CurrentRecipient::class));
        self::assertTrue($container->getAlias(CurrentRecipient::class)->isPublic());
        self::assertEquals([new Reference(NoCurrentRecipient::class)], $container->getDefinition(CheckedCurrentRecipient::class)->getArguments());
        self::assertTrue($container->hasDefinition(NoCurrentRecipient::class));
        self::assertSame('translator', (string) $container->getAlias(NotificationsServices::TRANSLATOR));
        self::assertSame(FlashToasts::class, (string) $container->getAlias(ToastPublisher::class));
        self::assertEquals([new Reference('request_stack'), new Reference(CurrentRecipient::class)], $container->getDefinition(FlashToasts::class)->getArguments());
        self::assertSame(MailerDigestSender::class, (string) $container->getAlias(DigestSender::class));
        self::assertTrue($container->hasDefinition(NotificationSchedule::class));
        self::assertFalse($container->hasDefinition(RemoteNotificationController::class));
        self::assertFalse($container->hasDefinition(InMemoryNotificationRepository::class));
        self::assertFalse($container->hasDefinition(DbalPreferences::class));

        $listener = $container->getDefinition(NotificationSchemaListener::class);
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), false, false], $listener->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $listener->getTag('doctrine.event_listener'));

        $defaults = $container->getDefinition(ConfiguredPreferences::class)->getArgument(0);
        self::assertInstanceOf(Definition::class, $defaults);
        self::assertSame(RecipientSettings::class, $defaults->getClass());
        self::assertSame([DigestFrequency::Never, null, [], null], $defaults->getArguments());
    }

    public function testTheCoreServices(): void
    {
        $container = $this->load([['types' => ['comment.replied' => ['channels' => ['site', 'email'], 'broadcast' => ['chat/slack'], 'collapse' => true, 'digest' => false, 'toast' => false, 'importance' => 'high', 'title' => 't', 'body' => 'b'], 'welcome' => []]]]);
        $types = $container->getDefinition(NotificationTypes::class)->getArguments();

        self::assertCount(2, $types);
        self::assertInstanceOf(Definition::class, $types[0]);
        self::assertSame(NotificationType::class, $types[0]->getClass());
        self::assertSame(['comment.replied', ['site', 'email'], ['chat/slack'], true, false, false, 'high', 't', 'b'], $types[0]->getArguments());
        self::assertInstanceOf(Definition::class, $types[1]);
        self::assertSame(['welcome', ['site'], [], false, true, true, 'medium', null, null], $types[1]->getArguments());

        $renderer = $container->getDefinition(NotificationRenderer::class);
        self::assertTrue($renderer->isPublic());
        self::assertEquals([new Reference(NotificationsServices::TRANSLATOR), new Reference(NotificationTypes::class), new Reference(RecipientDirectory::class), new TaggedIteratorArgument(NotificationsServices::SUBJECT_RESOLVER_TAG)], $renderer->getArguments());

        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $bus = new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        self::assertEquals([
            new Reference(NotificationTypes::class),
            new Reference(NotificationRepository::class),
            new Reference(NotificationPreferences::class),
            new Reference(NotificationRenderer::class),
            new Reference(RecipientDirectory::class),
            new Reference(ToastPublisher::class),
            new Reference('clock'),
            new Reference('notifier', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
            $logger,
        ], $container->getDefinition(NotificationDispatcher::class)->getArguments());

        $handler = $container->getDefinition(SendNotificationHandler::class);
        self::assertEquals([new Reference(NotificationDispatcher::class), new TaggedIteratorArgument(NotificationsServices::AUDIENCE_PROVIDER_TAG), $bus], $handler->getArguments());
        self::assertSame([['handles' => SendNotification::class]], $handler->getTag('messenger.message_handler'));

        $notifications = $container->getDefinition(Notifications::class);
        self::assertTrue($notifications->isPublic());
        self::assertEquals([new Reference(NotificationTypes::class), new Reference(SendNotificationHandler::class), $bus], $notifications->getArguments());

        $autoconfigured = $container->getAutoconfiguredInstanceof();
        self::assertSame([NotificationsServices::SUBJECT_RESOLVER_TAG => [[]]], $autoconfigured[SubjectResolver::class]->getTags());
        self::assertSame([NotificationsServices::AUDIENCE_PROVIDER_TAG => [[]]], $autoconfigured[AudienceProvider::class]->getTags());
    }

    public function testTheApplicationsServices(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'connection' => 'notifications',
            'preferences' => 'app.preferences',
            'recipient_directory' => 'app.directory',
            'current_recipient' => 'app.current',
            'web_push_sender' => 'app.push',
            'message_bus' => null,
            'toasts' => ['transport' => 'none'],
            'digest' => ['sender' => 'app.digests', 'limit' => 20],
        ]]);

        self::assertSame(InMemoryNotificationRepository::class, (string) $container->getAlias(NotificationRepository::class));
        self::assertTrue($container->getAlias(NotificationRepository::class)->isPublic());
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryNotificationRepository::class)->getTag('kernel.reset'));
        self::assertFalse($container->hasDefinition(DbalNotificationRepository::class));
        self::assertFalse($container->hasDefinition(NotificationSchemaListener::class));
        self::assertFalse($container->hasDefinition(ConfiguredPreferences::class));
        self::assertSame('app.preferences', (string) $container->getAlias(NotificationPreferences::class));
        self::assertSame('app.directory', (string) $container->getAlias(RecipientDirectory::class));
        self::assertFalse($container->hasDefinition(EmptyRecipientDirectory::class));
        self::assertSame(CheckedCurrentRecipient::class, (string) $container->getAlias(CurrentRecipient::class));
        self::assertEquals([new Reference('app.current')], $container->getDefinition(CheckedCurrentRecipient::class)->getArguments());
        self::assertFalse($container->hasDefinition(NoCurrentRecipient::class));
        self::assertEquals(new Reference('app.push'), $container->getDefinition(NotificationDispatcher::class)->getArgument(8));
        self::assertNull($container->getDefinition(Notifications::class)->getArgument(2));
        self::assertNull($container->getDefinition(SendNotificationHandler::class)->getArgument(2));
        self::assertSame(NullToasts::class, (string) $container->getAlias(ToastPublisher::class));
        self::assertSame([], $container->getDefinition(NullToasts::class)->getArguments());
        self::assertSame('app.digests', (string) $container->getAlias(DigestSender::class));
        self::assertFalse($container->hasDefinition(MailerDigestSender::class));
        self::assertSame(20, $container->getDefinition(Digests::class)->getArgument(6));
        self::assertNull($container->getDefinition(Digests::class)->getArgument(7));
    }

    public function testTheDbalPreferencesWithTheirDefaults(): void
    {
        $container = $this->load([['store' => 'memory', 'preferences' => 'dbal', 'connection' => 'other', 'defaults' => ['digest' => 'weekly', 'quiet_hours' => ['start' => '22:00', 'end' => '07:00', 'timezone' => 'America/Toronto'], 'opt_outs' => ['*:web_push'], 'locale' => 'fr']]]);
        $preferences = $container->getDefinition(DbalPreferences::class);

        self::assertTrue($preferences->isPublic());
        self::assertSame(DbalPreferences::class, (string) $container->getAlias(NotificationPreferences::class));
        self::assertEquals(new Reference('doctrine.dbal.other_connection'), $preferences->getArgument(0));
        $defaults = $preferences->getArgument(1);
        self::assertInstanceOf(Definition::class, $defaults);
        [$digest, $quiet, $optOuts, $locale] = $defaults->getArguments();
        self::assertSame([DigestFrequency::Weekly, ['*:web_push'], 'fr'], [$digest, $optOuts, $locale]);
        self::assertInstanceOf(Definition::class, $quiet);
        self::assertSame([QuietHours::class, 'of'], $quiet->getFactory());
        self::assertSame(['22:00', '07:00', 'America/Toronto'], $quiet->getArguments());

        $listener = $container->getDefinition(NotificationSchemaListener::class);
        self::assertEquals([new Reference('doctrine.dbal.other_connection'), true, false], $listener->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'other']], $listener->getTag('doctrine.event_listener'));
    }

    public function testMercureToasts(): void
    {
        $container = $this->load([['toasts' => ['transport' => 'mercure', 'hub' => 'app.hub', 'topic_prefix' => 'urn:x:', 'private' => false]]]);

        self::assertSame(MercureToasts::class, (string) $container->getAlias(ToastPublisher::class));
        self::assertEquals([new Reference('app.hub'), 'urn:x:', false, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(MercureToasts::class)->getArguments());
        self::assertEquals(new Reference('app.hub'), $container->getDefinition(CentreView::class)->getArgument(6));
        self::assertSame('urn:x:', $container->getDefinition(CentreView::class)->getArgument(7));
        self::assertNull($container->getDefinition(CentreView::class)->getArgument(8));
        self::assertSame(HubInterface::class, $this->load([])->getDefinition(CentreView::class)->getArgument(6) ?? HubInterface::class);
    }

    public function testTheDigests(): void
    {
        $container = $this->load([['digest' => ['from' => 'n@example.test', 'template' => 'mine.html.twig', 'at' => '06:00', 'weekday' => 'friday']]]);
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        self::assertEquals([new Reference('mailer'), new Reference(NotificationsServices::TRANSLATOR), 'n@example.test', 'mine.html.twig', $logger, null], $container->getDefinition(MailerDigestSender::class)->getArguments());
        self::assertEquals(new Reference('app.tracking'), $this->load([['digest' => ['tracking_consent' => 'app.tracking']]])->getDefinition(MailerDigestSender::class)->getArgument(5));
        $digests = $container->getDefinition(Digests::class);
        self::assertTrue($digests->isPublic());
        self::assertEquals([
            new Reference(NotificationRepository::class),
            new Reference(NotificationPreferences::class),
            new Reference(RecipientDirectory::class),
            new Reference(NotificationRenderer::class),
            new Reference(DigestSender::class),
            new Reference('clock'),
            50,
            new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $digests->getArguments());
        $handler = $container->getDefinition(DigestHandler::class);
        self::assertEquals([new Reference(Digests::class)], $handler->getArguments());
        self::assertSame([['handles' => SendDigests::class, 'method' => 'sweep'], ['handles' => SendDigest::class, 'method' => 'send']], $handler->getTag('messenger.message_handler'));
        self::assertSame(['06:00', 'friday', '03:30', 'UTC', true], $container->getDefinition(NotificationSchedule::class)->getArguments());
        self::assertSame([['name' => NotificationSchedule::NAME]], $container->getDefinition(NotificationSchedule::class)->getTag('scheduler.schedule_provider'));
    }

    public function testDigestsTurnedOff(): void
    {
        $container = $this->load([['digest' => false]]);

        self::assertFalse($container->hasDefinition(Digests::class));
        self::assertFalse($container->hasDefinition(DigestHandler::class));
        self::assertFalse($container->hasDefinition(MailerDigestSender::class));
        self::assertFalse($container->hasAlias(DigestSender::class));
        self::assertFalse($container->getDefinition(NotificationSchedule::class)->getArgument(4));
    }

    public function testTheRetentionAndItsSchedule(): void
    {
        $container = $this->load([['retention' => ['read_days' => 10, 'unread_days' => 20, 'purge_at' => '01:00'], 'schedule' => ['timezone' => 'Europe/Paris']]]);
        $retention = $container->getDefinition(Retention::class);

        self::assertTrue($retention->isPublic());
        self::assertEquals([new Reference(NotificationRepository::class), new Reference('clock'), 10, 20, 1000, 100], $retention->getArguments());
        self::assertEquals([new Reference(Retention::class)], $container->getDefinition(PurgeNotificationsHandler::class)->getArguments());
        self::assertSame([['handles' => PurgeNotifications::class]], $container->getDefinition(PurgeNotificationsHandler::class)->getTag('messenger.message_handler'));
        self::assertEquals([new Reference(Retention::class)], $container->getDefinition(PurgeCommand::class)->getArguments());
        self::assertSame([['command' => 'phil:notifications:purge', 'description' => 'Deletes the notifications kept longer than the retention.']], $container->getDefinition(PurgeCommand::class)->getTag('console.command'));
        self::assertSame(['07:00', 'monday', '01:00', 'Europe/Paris', true], $container->getDefinition(NotificationSchedule::class)->getArguments());
        self::assertFalse($this->load([['schedule' => false]])->hasDefinition(NotificationSchedule::class));
    }

    public function testTheCentre(): void
    {
        $container = $this->load([]);

        self::assertEquals([new Reference(NotificationsPass::TOKEN_MANAGER)], $container->getDefinition(CentreRequest::class)->getArguments());
        $controller = $container->getDefinition(CentreController::class);
        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertEquals([new Reference(NotificationRepository::class), new Reference(CurrentRecipient::class), new Reference(CentreRequest::class), new Reference('clock')], $controller->getArguments());
        $view = $container->getDefinition(CentreView::class);
        self::assertTrue($view->isPublic());
        self::assertEquals([
            new Reference(NotificationRepository::class),
            new Reference(CurrentRecipient::class),
            new Reference(NotificationRenderer::class),
            new Reference(NotificationPreferences::class),
            new Reference(CentreRequest::class),
            new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
            MercureToasts::PREFIX,
            new Reference(FlashToasts::class),
        ], $view->getArguments());

        foreach ([BellComponent::class, ListComponent::class, ToastsComponent::class] as $component) {
            self::assertEquals([new Reference(CentreView::class)], $container->getDefinition($component)->getArguments());
            self::assertTrue($container->getDefinition($component)->isAutoconfigured());
        }
    }

    public function testTheRemoteReceiving(): void
    {
        $container = $this->load([['remote' => ['origins' => ['events', 'shop']]]]);

        self::assertSame(ForwardingRemoteReceiver::class, (string) $container->getAlias(RemoteNotificationReceiver::class));
        self::assertEquals([new Reference(Notifications::class)], $container->getDefinition(ForwardingRemoteReceiver::class)->getArguments());
        $controller = $container->getDefinition(RemoteNotificationController::class);
        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertEquals([new Reference(RemoteNotificationReceiver::class), new Reference('clock'), ['events', 'shop'], new Reference(ProcessedWebhookRepository::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $controller->getArguments());

        $own = $this->load([['remote' => ['receiver' => 'app.receiver']]]);
        self::assertSame('app.receiver', (string) $own->getAlias(RemoteNotificationReceiver::class));
        self::assertFalse($own->hasDefinition(ForwardingRemoteReceiver::class));
        self::assertSame([], $own->getDefinition(RemoteNotificationController::class)->getArgument(2));
    }

    public function testTheBoundsOfTheConfiguration(): void
    {
        $container = $this->load([['store' => 'dbal', 'digest' => ['weekday' => 'monday', 'limit' => 1], 'retention' => ['read_days' => 1, 'unread_days' => 1]]]);
        self::assertSame(1, $container->getDefinition(Digests::class)->getArgument(6));
        self::assertSame(500, $this->load([['digest' => ['limit' => 500]]])->getDefinition(Digests::class)->getArgument(6));

        foreach ([['digest' => ['limit' => 0]], ['digest' => ['limit' => 501]], ['retention' => ['read_days' => 0]], ['retention' => ['unread_days' => 0]]] as $config) {
            try {
                $this->load([$config]);
                self::fail('A value out of its bounds is refused.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('Should be', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheTimesAreChecked(): void
    {
        foreach ([['digest' => ['at' => '7:00']], ['retention' => ['purge_at' => '25:00']], ['digest' => ['at' => 7]]] as $config) {
            try {
                $this->load([$config]);
                self::fail('A time of the wrong shape is refused.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('is written HH:MM, not', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testThePrependedConfiguration(): void
    {
        $container = new ContainerBuilder();
        $bundle = new PhilNotificationsBundle();
        $extension = $this->extension();
        $container->registerExtension($extension);
        $bundle->build($container);

        foreach (['framework', 'mercure', 'asset_mapper'] as $name) {
            $other = self::createStub(ExtensionInterface::class);
            $other->method('getAlias')->willReturn($name);
            $container->registerExtension($other);
        }

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['toasts' => ['transport' => 'mercure']]], $container->getExtensionConfig('phil_notifications'));
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => [CentreRequest::TOKEN_ID]]]], $container->getExtensionConfig('framework'));
        self::assertSame(
            [['paths' => [PhilNotificationsBundle::CONTROLLERS_DIR => '@phil/notifications-bundle', PhilNotificationsBundle::STYLES_DIR => 'phil-notifications', PhilNotificationsBundle::WORKER_DIR => 'phil-notifications-worker']]],
            $container->getExtensionConfig('asset_mapper'),
        );
    }

    public function testNoAssetPathIsWrittenToFrameworkWithoutAssetMapperBundle(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        $other = self::createStub(ExtensionInterface::class);
        $other->method('getAlias')->willReturn('framework');
        $container->registerExtension($other);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => [CentreRequest::TOKEN_ID]]]], $container->getExtensionConfig('framework'));
        self::assertSame([], $container->getExtensionConfig('asset_mapper'));
    }

    public function testNothingIsPrependedWithoutTheOtherBundles(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('phil_notifications'));
    }

    public function testTheAssetsAreThere(): void
    {
        self::assertFileExists(PhilNotificationsBundle::CONTROLLERS_DIR.'/notifications_controller.js');
        self::assertFileExists(PhilNotificationsBundle::STYLES_DIR.'/notifications.css');
        self::assertFileExists(PhilNotificationsBundle::CONTROLLERS_DIR.'/web_push_controller.js');
        self::assertFileExists(PhilNotificationsBundle::WORKER_DIR.'/push-worker.js');
    }

    public function testTheCompiledContainerFallsBackOnWhatIsMissing(): void
    {
        $container = $this->load([['store' => 'memory']]);
        $container->register('security.csrf.token_manager', CsrfTokenManager::class);
        $container->register('clock', MockClock::class);
        $container->setParameter('kernel.debug', false);
        new NotificationsPass()->process($container);

        self::assertSame(PlainTranslator::class, (string) $container->getAlias(NotificationsServices::TRANSLATOR));
        self::assertTrue($container->hasDefinition(PlainTranslator::class));
        self::assertSame(NoCurrentRecipient::class, $container->getDefinition(NoCurrentRecipient::class)->getClass());
        self::assertFalse($container->hasDefinition(MailerDigestSender::class));
        self::assertFalse($container->hasAlias(DigestSender::class));
        self::assertFalse($container->hasDefinition(Digests::class));
        self::assertFalse($container->hasDefinition(DigestHandler::class));
        self::assertFalse($container->getDefinition(NotificationSchedule::class)->getArgument(4));
    }

    public function testTheCompiledContainerKeepsWhatIsThere(): void
    {
        $container = $this->load([['store' => 'memory', 'schedule' => false, 'current_recipient' => 'app.current']]);
        foreach (['security.csrf.token_manager', 'translator', 'security.token_storage', 'mailer'] as $id) {
            $container->register($id, stdClass::class);
        }

        new NotificationsPass()->process($container);

        self::assertSame('translator', (string) $container->getAlias(NotificationsServices::TRANSLATOR));
        self::assertFalse($container->hasDefinition(PlainTranslator::class));
        self::assertEquals([new Reference('app.current')], $container->getDefinition(CheckedCurrentRecipient::class)->getArguments());
        self::assertTrue($container->hasDefinition(MailerDigestSender::class));
        self::assertTrue($container->hasDefinition(Digests::class));
    }

    public function testWithoutMailerOrScheduleNothingBreaks(): void
    {
        $container = $this->load([['store' => 'memory', 'schedule' => false]]);
        $container->register('security.csrf.token_manager', stdClass::class);
        new NotificationsPass()->process($container);

        self::assertFalse($container->hasDefinition(Digests::class));
        self::assertFalse($container->hasDefinition(NotificationSchedule::class));
    }

    public function testTheApplicationsOwnCurrentRecipientAndDigestSenderAreLeftAlone(): void
    {
        $container = $this->load([['store' => 'memory', 'current_recipient' => 'app.current', 'digest' => ['sender' => 'app.digests']]]);
        $container->register('security.csrf.token_manager', stdClass::class);
        new NotificationsPass()->process($container);

        self::assertFalse($container->hasDefinition(NoCurrentRecipient::class));
        self::assertEquals([new Reference('app.current')], $container->getDefinition(CheckedCurrentRecipient::class)->getArguments());
        self::assertTrue($container->hasDefinition(Digests::class));
    }

    public function testTheCentreForTheUsersOfSymfonySecurityNeedsACurrentRecipient(): void
    {
        $container = $this->load([['store' => 'memory']]);
        $container->register('security.csrf.token_manager', stdClass::class);
        $container->register('security.token_storage', stdClass::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"phil_notifications.current_recipient" names no service. Name a CurrentRecipient service that returns the stable identifier notifications are sent to');

        new NotificationsPass()->process($container);
    }

    public function testWithoutTheCentreSymfonySecurityNeedsNoCurrentRecipient(): void
    {
        $container = $this->load([['store' => 'memory']]);
        $container->removeDefinition(CentreRequest::class);
        $container->register('security.token_storage', stdClass::class);

        new NotificationsPass()->process($container);

        self::assertTrue($container->hasDefinition(NoCurrentRecipient::class));
    }

    public function testTheCompiledContainerNeedsTheCsrfProtection(): void
    {
        $container = $this->load([['store' => 'memory']]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil/notifications-bundle checks the changes of the notification centre with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Remove "csrf_protection: false" from the framework configuration or set it to true: the token "phil_notifications" is stateless and needs no session.');

        new NotificationsPass()->process($container);
    }

    public function testThePassLeavesAContainerWithoutTheBundleAlone(): void
    {
        $container = new ContainerBuilder();
        new NotificationsPass()->process($container);

        self::assertTrue($container->hasDefinition(PlainTranslator::class));
        self::assertFalse($container->hasDefinition(NoCurrentRecipient::class));
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilNotificationsBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof NotificationsPass));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilNotificationsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
