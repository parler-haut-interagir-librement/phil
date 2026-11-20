<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\DependencyInjection;

use LogicException;
use Minishlink\WebPush\WebPush;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\NotificationsBundle\Application\CheckedCurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\AudienceProvider;
use Phil\NotificationsBundle\Application\Contract\ChannelSender;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\DigestSender;
use Phil\NotificationsBundle\Application\Contract\NotificationPreferences;
use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Phil\NotificationsBundle\Application\Contract\RemoteNotificationReceiver;
use Phil\NotificationsBundle\Application\Contract\SubjectResolver;
use Phil\NotificationsBundle\Application\Contract\ToastPublisher;
use Phil\NotificationsBundle\Application\Delivery\DeliveryMode;
use Phil\NotificationsBundle\Application\Delivery\DeliveryPolicy;
use Phil\NotificationsBundle\Application\Delivery\RecipientRateLimit;
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
use Phil\NotificationsBundle\Application\WebPush\PushSubscriber;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Infrastructure\CircuitBreaker\ChannelBreakers;
use Phil\NotificationsBundle\Infrastructure\Console\PurgeCommand;
use Phil\NotificationsBundle\Infrastructure\Console\VapidKeysCommand;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPreferences;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationSchemaListener;
use Phil\NotificationsBundle\Infrastructure\Http\CentreController;
use Phil\NotificationsBundle\Infrastructure\Http\CentreRequest;
use Phil\NotificationsBundle\Infrastructure\Http\FlashToasts;
use Phil\NotificationsBundle\Infrastructure\Http\NullToasts;
use Phil\NotificationsBundle\Infrastructure\Http\PushSubscriptionController;
use Phil\NotificationsBundle\Infrastructure\Idempotency\NotificationDedupe;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerChannelSender;
use Phil\NotificationsBundle\Infrastructure\Mailer\MailerDigestSender;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryPushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Mercure\MercureToasts;
use Phil\NotificationsBundle\Infrastructure\Notifier\EmptyRecipientDirectory;
use Phil\NotificationsBundle\Infrastructure\Notifier\NotifierChannelSender;
use Phil\NotificationsBundle\Infrastructure\Remote\RemoteNotificationController;
use Phil\NotificationsBundle\Infrastructure\Scheduler\NotificationSchedule;
use Phil\NotificationsBundle\Infrastructure\Security\NoCurrentRecipient;
use Phil\NotificationsBundle\Infrastructure\Twig\BellComponent;
use Phil\NotificationsBundle\Infrastructure\Twig\CentreView;
use Phil\NotificationsBundle\Infrastructure\Twig\ListComponent;
use Phil\NotificationsBundle\Infrastructure\Twig\ToastsComponent;
use Phil\NotificationsBundle\Infrastructure\Twig\WebPushComponent;
use Phil\NotificationsBundle\Infrastructure\WebPush\MinishlinkWebPushSender;
use Phil\NotificationsBundle\Infrastructure\WebPush\VapidKeys;
use Phil\RateLimit\RateLimitGuard;
use Phil\StandardWebhooks\ProcessedWebhookRepository;
use Phil\StandardWebhooks\VerifiedWebhook;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Mailer\Transport\TransportInterface as MailerTransport;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The bundle's services: the repository and the preferences on the configured store, the types, the renderer, the
 * dispatcher and its delivery policy, the entry point on the services of the application, the toasts on the configured transport, the
 * digests, the purge and their schedule, the centre's endpoints and components, the receiving of remote
 * notifications, and Web Push.
 */
final class NotificationsServices
{
    public const string SUBJECT_RESOLVER_TAG = 'phil_notifications.subject_resolver';

    public const string AUDIENCE_PROVIDER_TAG = 'phil_notifications.audience_provider';

    public const string TRANSLATOR = 'phil_notifications.translator';

    public const string CHANNEL_SENDER_TAG = 'phil_notifications.channel_sender';

    /** The hidden parameter of the cache pool of the window of duplicates, checked by `NotificationsPass`. */
    public const string DEDUPE_POOL_PARAMETER = '.phil_notifications.dedupe_pool';

    /**
     * @param array{store: string, connection: string, preferences: string, recipient_directory: ?string, current_recipient: ?string, web_push_sender: ?string, message_bus: ?string, defaults: array{digest: string, quiet_hours?: array{start: string, end: string, timezone: string}, opt_outs: list<string>, locale: ?string}, delivery: array{single_server: bool, from: ?string, dedupe: array{enabled: bool, ttl: int, store: ?string, cache_pool: ?string, lock_factory: string}, rate_limiter: ?string}, types: array<string, array{channels: list<string>, broadcast: list<string>, collapse: bool, digest: bool, toast: bool, importance: string, title: ?string, body: ?string, delivery: string, dedupe_ttl: ?int}>, toasts: array{transport: string, hub: string, topic_prefix: string, private: bool}, digest: array{enabled: bool, sender: ?string, from: ?string, template: string, tracking_consent: ?string, at: string, weekday: string, limit: int}, retention: array{read_days: int, unread_days: int, purge_at: string}, schedule: array{enabled: bool, timezone: string}, remote: array{enabled: bool, receiver: ?string, origins: list<string>}, web_push: array{enabled: bool, subject: ?string, public_key: string, private_key: string, ttl: int, push_services: list<string>, max_subscriptions: positive-int, worker: ?string, http_client: ?string, timeout: float}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(SubjectResolver::class)->addTag(self::SUBJECT_RESOLVER_TAG);
        $container->registerForAutoconfiguration(AudienceProvider::class)->addTag(self::AUDIENCE_PROVIDER_TAG);
        $container->registerForAutoconfiguration(ChannelSender::class)->addTag(self::CHANNEL_SENDER_TAG);
        $bus = null === $config['message_bus'] ? null : new Reference($config['message_bus'], ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        self::stores($container, $config);
        self::collaborators($container, $config);
        $webPushSender = self::webPush($container, $config, $logger);

        $container->register(NotificationTypes::class, NotificationTypes::class)->setArguments(self::types($config['types']));
        $container->register(NotificationRenderer::class, NotificationRenderer::class)->setArguments([
            new Reference(self::TRANSLATOR),
            new Reference(NotificationTypes::class),
            new Reference(RecipientDirectory::class),
            new TaggedIteratorArgument(self::SUBJECT_RESOLVER_TAG),
        ])->setPublic(true);
        $container->register(NotificationDispatcher::class, NotificationDispatcher::class)->setArguments([
            new Reference(NotificationTypes::class),
            new Reference(NotificationRepository::class),
            new Reference(NotificationPreferences::class),
            new Reference(NotificationRenderer::class),
            new Reference(RecipientDirectory::class),
            new Reference(ToastPublisher::class),
            new Reference('clock'),
            new Reference('notifier', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            $webPushSender,
            $logger,
            self::delivery($container, $config),
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
        $container->register(SendNotificationHandler::class, SendNotificationHandler::class)
            ->setArguments([new Reference(NotificationDispatcher::class), new TaggedIteratorArgument(self::AUDIENCE_PROVIDER_TAG), $bus])
            ->addTag('messenger.message_handler', ['handles' => SendNotification::class]);
        $container->register(Notifications::class, Notifications::class)
            ->setArguments([new Reference(NotificationTypes::class), new Reference(SendNotificationHandler::class), $bus])
            ->setPublic(true);

        self::toasts($container, $config['toasts'], $logger);
        self::digests($container, $config, $bus, $logger);
        self::retention($container, $config);
        self::centre($container, $config['toasts']);
        self::remote($container, $config['remote']);
    }

    /**
     * What a delivery checks and sends through: the channel senders, which `NotificationsPass` lists, the window of
     * duplicates on phil/idempotency, the rate limit on a limiter of symfony/rate-limiter, and the circuit breakers of
     * phil/circuit-breaker when the application declares them.
     *
     * @param array{delivery: array{single_server: bool, from: ?string, dedupe: array{enabled: bool, ttl: int, store: ?string, cache_pool: ?string, lock_factory: string}, rate_limiter: ?string}, ...} $config
     *
     * @throws InvalidConfigurationException when the window or the limit is configured without its package
     */
    private static function delivery(ContainerBuilder $container, array $config): Reference
    {
        $delivery = $config['delivery'];
        $container->register(NotifierChannelSender::class, NotifierChannelSender::class)->setArguments([
            new Reference('texter', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('chatter', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

        if (interface_exists(MailerTransport::class)) {
            $container->register(MailerChannelSender::class, MailerChannelSender::class)
                ->setArguments([new Reference(NotificationsPass::MAILER_TRANSPORTS, ContainerInterface::NULL_ON_INVALID_REFERENCE), $delivery['from']]);
        }

        $dedupe = null;

        if ($delivery['dedupe']['enabled']) {
            if (!self::installed(IdempotencyGuard::class)) {
                throw new InvalidConfigurationException('phil_notifications.delivery.dedupe needs phil/idempotency: run "composer require phil/idempotency", or set "enabled" to false.');
            }

            $store = $delivery['dedupe']['store'];

            if (null === $store) {
                $pool = (string) $delivery['dedupe']['cache_pool'];
                $store = CacheIdempotencyStore::class.'.phil_notifications';
                $container->register($store, CacheIdempotencyStore::class)
                    ->setArguments([new Reference($pool), new Reference($delivery['dedupe']['lock_factory']), new Reference('clock')]);

                if (!$delivery['single_server']) {
                    $container->setParameter(self::DEDUPE_POOL_PARAMETER, $pool);
                }
            }

            $guard = new Definition(IdempotencyGuard::class, [new Reference($store), new Reference('clock'), $delivery['dedupe']['ttl']]);
            $container->register(NotificationDedupe::class, NotificationDedupe::class)->setArguments([$guard, $delivery['dedupe']['ttl']]);
            $dedupe = new Reference(NotificationDedupe::class);
        }

        $rateLimit = null;

        if (null !== $delivery['rate_limiter']) {
            RateLimitGuard::requireComponent(interface_exists(RateLimiterFactoryInterface::class), 'phil_notifications.delivery.rate_limiter');
            $limiter = 'limiter.'.$delivery['rate_limiter'];
            RateLimitGuard::watch($container, $limiter, 'phil_notifications.delivery.rate_limiter', $delivery['single_server']);
            $container->register(RecipientRateLimit::class, RecipientRateLimit::class)->setArguments([new Reference($limiter)]);
            $rateLimit = new Reference(RecipientRateLimit::class);
        }

        $breakers = self::installed(CircuitBreakers::class)
            ? new Definition(ChannelBreakers::class, [new Reference(CircuitBreakers::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            : null;

        $container->register(DeliveryPolicy::class, DeliveryPolicy::class)
            ->setArguments([new IteratorArgument([]), $dedupe, $rateLimit, $breakers]);

        return new Reference(DeliveryPolicy::class);
    }

    /**
     * @param array{store: string, connection: string, preferences: string, defaults: array{digest: string, quiet_hours?: array{start: string, end: string, timezone: string}, opt_outs: list<string>, locale: ?string}, web_push: array{enabled: bool, ...}, ...} $config
     */
    private static function stores(ContainerBuilder $container, array $config): void
    {
        $connection = new Reference('doctrine.dbal.'.$config['connection'].'_connection');
        $quiet = $config['defaults']['quiet_hours'] ?? null;
        $defaults = new Definition(RecipientSettings::class, [
            DigestFrequency::from($config['defaults']['digest']),
            null === $quiet ? null : new Definition(QuietHours::class, [$quiet['start'], $quiet['end'], $quiet['timezone']])->setFactory([QuietHours::class, 'of']),
            $config['defaults']['opt_outs'],
            $config['defaults']['locale'],
        ]);

        if ('memory' === $config['store']) {
            $container->register(InMemoryNotificationRepository::class, InMemoryNotificationRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(NotificationRepository::class, InMemoryNotificationRepository::class)->setPublic(true);
        } else {
            $container->register(DbalNotificationRepository::class, DbalNotificationRepository::class)->setArguments([$connection]);
            $container->setAlias(NotificationRepository::class, DbalNotificationRepository::class)->setPublic(true);
        }

        if ('dbal' === $config['store'] || 'dbal' === $config['preferences']) {
            $container->register(NotificationSchemaListener::class, NotificationSchemaListener::class)
                ->setArguments([$connection, 'dbal' === $config['preferences'], 'dbal' === $config['store'] && $config['web_push']['enabled']])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        match ($config['preferences']) {
            'config' => $container->register(ConfiguredPreferences::class, ConfiguredPreferences::class)->setArguments([$defaults]),
            'dbal' => $container->register(DbalPreferences::class, DbalPreferences::class)->setArguments([$connection, $defaults])->setPublic(true),
            default => null,
        };

        $container->setAlias(NotificationPreferences::class, match ($config['preferences']) {
            'config' => ConfiguredPreferences::class,
            'dbal' => DbalPreferences::class,
            default => $config['preferences'],
        })->setPublic(true);
    }

    /**
     * @param array{recipient_directory: ?string, current_recipient: ?string, ...} $config
     */
    private static function collaborators(ContainerBuilder $container, array $config): void
    {
        if (null === $config['recipient_directory']) {
            $container->register(EmptyRecipientDirectory::class, EmptyRecipientDirectory::class);
        }

        $container->setAlias(RecipientDirectory::class, $config['recipient_directory'] ?? EmptyRecipientDirectory::class);

        // No default reads Symfony Security's user identifier, which can be an email address: the application names its
        // members by a stable identifier through a service of its own, or the centre shows no one's notifications.
        if (null === $config['current_recipient']) {
            $container->register(NoCurrentRecipient::class, NoCurrentRecipient::class);
        }

        $container->register(CheckedCurrentRecipient::class, CheckedCurrentRecipient::class)
            ->setArguments([new Reference($config['current_recipient'] ?? NoCurrentRecipient::class)]);
        $container->setAlias(CurrentRecipient::class, CheckedCurrentRecipient::class)->setPublic(true);
        $container->setAlias(self::TRANSLATOR, 'translator');
    }

    /**
     * @param array<string, array{channels: list<string>, broadcast: list<string>, collapse: bool, digest: bool, toast: bool, importance: string, title: ?string, body: ?string, delivery: string, dedupe_ttl: ?int}> $types
     *
     * @return list<Definition>
     */
    private static function types(array $types): array
    {
        $definitions = [];

        foreach ($types as $key => $type) {
            $definitions[] = new Definition(NotificationType::class, [
                $key,
                $type['channels'],
                $type['broadcast'],
                $type['collapse'],
                $type['digest'],
                $type['toast'],
                $type['importance'],
                $type['title'],
                $type['body'],
                DeliveryMode::from($type['delivery']),
                $type['dedupe_ttl'],
            ]);
        }

        return $definitions;
    }

    /**
     * @param array{transport: string, hub: string, topic_prefix: string, private: bool} $toasts
     */
    private static function toasts(ContainerBuilder $container, array $toasts, Reference $logger): void
    {
        $class = match ($toasts['transport']) {
            'mercure' => MercureToasts::class,
            'flash' => FlashToasts::class,
            default => NullToasts::class,
        };

        $definition = $container->register($class, $class);
        $container->setAlias(ToastPublisher::class, $class);

        if (MercureToasts::class === $class) {
            $definition->setArguments([new Reference($toasts['hub']), $toasts['topic_prefix'], $toasts['private'], $logger]);
        } elseif (FlashToasts::class === $class) {
            $definition->setArguments([new Reference('request_stack'), new Reference(CurrentRecipient::class)]);
        }
    }

    /**
     * @param array{digest: array{enabled: bool, sender: ?string, from: ?string, template: string, tracking_consent: ?string, at: string, weekday: string, limit: int}, ...} $config
     */
    private static function digests(ContainerBuilder $container, array $config, ?Reference $bus, Reference $logger): void
    {
        $digest = $config['digest'];

        if (!$digest['enabled']) {
            return;
        }

        if (null === $digest['sender']) {
            $container->register(MailerDigestSender::class, MailerDigestSender::class)
                ->setArguments([
                    new Reference(NotificationsPass::MAILER_TRANSPORTS),
                    new Reference(self::TRANSLATOR),
                    $digest['from'],
                    $digest['template'],
                    $logger,
                    null === $digest['tracking_consent'] ? null : new Reference($digest['tracking_consent']),
                    new Reference('twig.mime_body_renderer', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);
        }

        $container->setAlias(DigestSender::class, $digest['sender'] ?? MailerDigestSender::class);
        $container->register(Digests::class, Digests::class)->setArguments([
            new Reference(NotificationRepository::class),
            new Reference(NotificationPreferences::class),
            new Reference(RecipientDirectory::class),
            new Reference(NotificationRenderer::class),
            new Reference(DigestSender::class),
            new Reference('clock'),
            $digest['limit'],
            $bus,
        ])->setPublic(true);
        $container->register(DigestHandler::class, DigestHandler::class)
            ->setArguments([new Reference(Digests::class)])
            ->addTag('messenger.message_handler', ['handles' => SendDigests::class, 'method' => 'sweep'])
            ->addTag('messenger.message_handler', ['handles' => SendDigest::class, 'method' => 'send']);
    }

    /**
     * @param array{digest: array{enabled: bool, at: string, weekday: string, ...}, retention: array{read_days: int, unread_days: int, purge_at: string}, schedule: array{enabled: bool, timezone: string}, ...} $config
     */
    private static function retention(ContainerBuilder $container, array $config): void
    {
        $container->register(Retention::class, Retention::class)
            ->setArguments([new Reference(NotificationRepository::class), new Reference('clock'), $config['retention']['read_days'], $config['retention']['unread_days'], Retention::BATCH, Retention::BATCHES])
            ->setPublic(true);
        $container->register(PurgeNotificationsHandler::class, PurgeNotificationsHandler::class)
            ->setArguments([new Reference(Retention::class)])
            ->addTag('messenger.message_handler', ['handles' => PurgeNotifications::class]);
        $container->register(PurgeCommand::class, PurgeCommand::class)
            ->setArguments([new Reference(Retention::class)])
            ->addTag('console.command', ['command' => 'phil:notifications:purge', 'description' => 'Deletes the notifications kept longer than the retention.']);

        if ($config['schedule']['enabled'] && interface_exists(ScheduleProviderInterface::class)) {
            $container->register(NotificationSchedule::class, NotificationSchedule::class)
                ->setArguments([
                    $config['digest']['at'],
                    $config['digest']['weekday'],
                    $config['retention']['purge_at'],
                    $config['schedule']['timezone'],
                    $config['digest']['enabled'],
                ])
                ->addTag('scheduler.schedule_provider', ['name' => NotificationSchedule::NAME]);
        }
    }

    /**
     * @param array{transport: string, hub: string, topic_prefix: string, ...} $toasts
     */
    private static function centre(ContainerBuilder $container, array $toasts): void
    {
        $container->register(CentreRequest::class, CentreRequest::class)
            ->setArguments([new Reference(NotificationsPass::TOKEN_MANAGER)]);
        $container->register(CentreController::class, CentreController::class)
            ->setArguments([
                new Reference(NotificationRepository::class),
                new Reference(CurrentRecipient::class),
                new Reference(CentreRequest::class),
                new Reference('clock'),
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(CentreView::class, CentreView::class)
            ->setArguments([
                new Reference(NotificationRepository::class),
                new Reference(CurrentRecipient::class),
                new Reference(NotificationRenderer::class),
                new Reference(NotificationPreferences::class),
                new Reference(CentreRequest::class),
                new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                'mercure' === $toasts['transport'] ? new Reference($toasts['hub']) : null,
                $toasts['topic_prefix'],
                'flash' === $toasts['transport'] ? new Reference(FlashToasts::class) : null,
            ])
            ->setPublic(true);

        if (!class_exists(AsTwigComponent::class)) {
            return;
        }

        foreach ([BellComponent::class, ListComponent::class, ToastsComponent::class] as $component) {
            $container->register($component, $component)
                ->setArguments([new Reference(CentreView::class)])
                ->setAutoconfigured(true);
        }
    }

    /**
     * @param array{enabled: bool, receiver: ?string, origins: list<string>} $remote
     */
    private static function remote(ContainerBuilder $container, array $remote): void
    {
        if (!$remote['enabled'] || !class_exists(VerifiedWebhook::class)) {
            return;
        }

        if (null === $remote['receiver']) {
            $container->register(ForwardingRemoteReceiver::class, ForwardingRemoteReceiver::class)
                ->setArguments([new Reference(Notifications::class)]);
        }

        $container->setAlias(RemoteNotificationReceiver::class, $remote['receiver'] ?? ForwardingRemoteReceiver::class);
        $container->register(RemoteNotificationController::class, RemoteNotificationController::class)
            ->setArguments([
                new Reference(RemoteNotificationReceiver::class),
                new Reference('clock'),
                $remote['origins'],
                new Reference(ProcessedWebhookRepository::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }

    /**
     * The subscriptions on the configured store, the endpoints and the button, and the sender of minishlink/web-push
     * unless the application names its own; returns the sender of the `web_push` channel, if any.
     *
     * @param array{store: string, connection: string, web_push_sender: ?string, web_push: array{enabled: bool, subject: ?string, public_key: string, private_key: string, ttl: int, push_services: list<string>, max_subscriptions: positive-int, worker: ?string, http_client: ?string, timeout: float}, ...} $config
     *
     * @throws LogicException when Web Push is enabled without a sender of the application's and without the library
     */
    private static function webPush(ContainerBuilder $container, array $config, Reference $logger): ?Reference
    {
        $container->register(VapidKeysCommand::class, VapidKeysCommand::class)
            ->addTag('console.command', ['command' => 'phil:notifications:vapid-keys', 'description' => 'Writes a new pair of VAPID keys for Web Push.']);
        $webPush = $config['web_push'];

        if (!$webPush['enabled']) {
            return self::service($config['web_push_sender']);
        }

        if ('memory' === $config['store']) {
            $container->register(InMemoryPushSubscriptions::class, InMemoryPushSubscriptions::class)
                ->setArguments([$webPush['max_subscriptions']])
                ->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(PushSubscriptions::class, InMemoryPushSubscriptions::class)->setPublic(true);
        } else {
            $container->register(DbalPushSubscriptions::class, DbalPushSubscriptions::class)
                ->setArguments([new Reference('doctrine.dbal.'.$config['connection'].'_connection'), new Reference('clock'), $webPush['max_subscriptions']]);
            $container->setAlias(PushSubscriptions::class, DbalPushSubscriptions::class)->setPublic(true);
        }

        $container->register(PushSubscriber::class, PushSubscriber::class)
            ->setArguments([new Reference(PushSubscriptions::class), $webPush['push_services']])
            ->setPublic(true);
        $container->register(PushSubscriptionController::class, PushSubscriptionController::class)
            ->setArguments([new Reference(PushSubscriber::class), new Reference(CurrentRecipient::class), new Reference(CentreRequest::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        if (class_exists(AsTwigComponent::class)) {
            $container->register(WebPushComponent::class, WebPushComponent::class)
                ->setArguments([
                    new Reference(CentreView::class),
                    $webPush['public_key'],
                    new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    new Reference('assets.packages', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    $webPush['worker'],
                ])
                ->setAutoconfigured(true);
        }

        if (null !== $config['web_push_sender']) {
            return new Reference($config['web_push_sender']);
        }

        if (!self::installed(WebPush::class, NoPrivateNetworkHttpClient::class)) {
            throw new LogicException('phil/notifications-bundle sends Web Push with minishlink/web-push and Symfony HttpClient, which are not installed: run "composer require minishlink/web-push nyholm/psr7 symfony/http-client", or name a WebPushSender service of the application in "phil_notifications.web_push_sender".');
        }

        $http = null === $webPush['http_client']
            ? new Definition(HttpClientInterface::class)->setFactory([HttpClient::class, 'create'])
            : new Reference($webPush['http_client']);
        $container->register(VapidKeys::class, VapidKeys::class)
            ->setArguments([$webPush['subject'], $webPush['public_key'], $webPush['private_key']]);
        $container->register(MinishlinkWebPushSender::class, MinishlinkWebPushSender::class)
            ->setArguments([
                new Reference(NotificationTypes::class),
                new Reference(PushSubscriptions::class),
                new Reference(VapidKeys::class),
                new Definition(NoPrivateNetworkHttpClient::class, [$http]),
                $webPush['ttl'],
                $logger,
                $webPush['timeout'],
            ])
            ->setPublic(true);

        return new Reference(MinishlinkWebPushSender::class);
    }

    private static function service(?string $id): ?Reference
    {
        return null === $id ? null : new Reference($id);
    }

    /**
     * Whether every one of these classes can be loaded.
     *
     * @internal
     */
    public static function installed(string ...$classes): bool
    {
        return [] === array_filter($classes, static fn (string $class): bool => !class_exists($class));
    }
}
