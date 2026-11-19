<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Monolog\Handler\AbstractProcessingHandler;
use Override;
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
use Phil\IpAnonymizer\Truncation;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\RateLimit\RateLimitGuard;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The error tracking of a Symfony application, wired from its configuration:
 *
 * ```yaml
 * phil_error_tracking:
 *     release: '%env(default::APP_RELEASE)%'
 *     alerts: { transport: discord }
 *     browser: { enabled: true }
 * ```
 *
 * The capture points — the exception of a request, a message a worker failed, the error of a command, the records of
 * the Monolog handler, the browsers' reports — all go through one `SafeCapture`, which never throws and never loops.
 * The groups and events are kept through DBAL; the services keep nothing between requests but the throwables already
 * captured, forgotten when the services are reset.
 */
final class PhilErrorTrackingBundle extends AbstractBundle
{
    public const string MONOLOG_HANDLER = 'phil_error_tracking.monolog_handler';

    /** The name of the limiter of the browsers' endpoint among those of phil/rate-limit-bundle. */
    public const string BROWSER_LIMITER = 'phil_error_tracking.browser';

    private const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_error_tracking';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RateLimitGuard());
        $container->addCompilerPass(new AlertsPass());
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The Stimulus controller under the name Symfony UX gives the controllers of a package, for AssetMapper to serve.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/error-tracking-bundle']]);
        }

        if ($builder->hasExtension('phil_notifications')) {
            $container->extension('phil_notifications', ['types' => [NotificationAlerter::TYPE => [
                'channels' => ['site', 'email'],
                'collapse' => true,
                'digest' => false,
                'importance' => 'high',
            ]]]);
        }
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('release')->info('The release that runs, such as a version or a commit; regressions are told by it.')->defaultNull()->end()
                ->scalarNode('environment')->defaultValue('%kernel.environment%')->end()
                ->scalarNode('project_dir')->info("The frames under it and outside its vendor/ are the application's.")->defaultValue('%kernel.project_dir%')->end()
                ->floatNode('sample_rate')->info('The share of the events kept, from 0.0 to 1.0.')->min(0.0)->max(1.0)->defaultValue(1.0)->end()
                ->integerNode('max_frames')->min(1)->max(500)->defaultValue(EventFactory::DEFAULT_MAX_FRAMES)->end()
                ->integerNode('max_chain')->min(1)->max(50)->defaultValue(EventFactory::DEFAULT_MAX_CHAIN)->end()
                ->arrayNode('scrub_fields')
                    ->info('Parts of a name that make a header or a tag sensitive, beside the defaults of the scrubber.')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('token_path_prefixes')
                    ->info('Path prefixes, such as "/reset-password/reset/", whose next segment is a token, filtered in the address, the Referer and the Origin.')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('ignored_exceptions')
                    ->info('Exception classes never captured, with their subclasses.')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('client_address')
                    ->info("The client's address truncated by phil/ip-anonymizer; never kept raw.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('ipv4_prefix')->min(0)->max(32)->defaultValue(Truncation::DEFAULT_IPV4_PREFIX)->end()
                        ->integerNode('ipv6_prefix')->min(0)->max(128)->defaultValue(Truncation::DEFAULT_IPV6_PREFIX)->end()
                    ->end()
                ->end()
                ->arrayNode('rate_limit')
                    ->info('The events kept per group, counted by symfony/rate-limiter.')
                    ->canBeDisabled()
                    ->children()
                        ->integerNode('limit')->min(1)->defaultValue(30)->end()
                        ->scalarNode('interval')->defaultValue('1 minute')->end()
                        ->scalarNode('cache_pool')->info('A pool on a shared store: one local to each server is refused in the prod environment.')->defaultValue('cache.app')->end()
                        ->booleanNode('single_server')->info('Accepts a pool local to the server in the prod environment, for an application on one server.')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('capture')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('http')
                            ->canBeDisabled()
                            ->children()
                                ->booleanNode('client_errors')->info('Captures the HTTP exceptions of a status below 500 too.')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->arrayNode('messenger')
                            ->canBeDisabled()
                            ->children()
                                ->booleanNode('retries')->info('Captures each failed attempt, not only the last one.')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->booleanNode('console')->defaultTrue()->end()
                        ->scalarNode('monolog_level')->info('The level from which the Monolog handler captures a record.')->defaultValue('error')->end()
                    ->end()
                ->end()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')->defaultValue('default')->cannotBeEmpty()->end()
                        ->integerNode('events_per_group')->min(1)->max(10_000)->defaultValue(DbalErrorStore::DEFAULT_EVENTS_PER_GROUP)->end()
                        ->integerNode('retention')->info('Days after which phil:error-tracking:purge removes an event, and a group not seen since.')->min(0)->defaultValue(30)->end()
                    ->end()
                ->end()
                ->arrayNode('alerts')
                    ->info('An alert for the first event of a group and for a regression: a notification to the recipients or the audience named here, else a chat message when the notifier has a chatter.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('transport')->info('The chatter transport, as the notifier names it; its default one when null.')->defaultNull()->end()
                        ->booleanNode('async')->info('Sends the chat message through Messenger rather than in the request that failed.')->defaultTrue()->end()
                        ->arrayNode('recipients')
                            ->info('The identifiers phil/notifications-bundle notifies.')
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('audience')->info('The audience of phil/notifications-bundle notified, instead of recipients.')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('health_check')
                    ->info('A check of phil/monitoring-bundle, when it is installed: whether the store answers and whether new groups are flooding in.')
                    ->canBeDisabled()
                    ->children()
                        ->integerNode('new_groups')->info('The new groups within the window that make the check degraded; 0 to ignore them.')->min(0)->max(ErrorStoreCheck::MAX_NEW_GROUPS)->defaultValue(20)->end()
                        ->integerNode('window')->info('The length of that window, in seconds, up to a day.')->min(1)->max(86_400)->defaultValue(900)->end()
                    ->end()
                ->end()
                ->arrayNode('admin')
                    ->info('The administration pages, off unless enabled; they need SecurityBundle, its CSRF protection and TwigBundle.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('role')->defaultValue('ROLE_ADMIN')->cannotBeEmpty()->end()
                        ->scalarNode('layout')->defaultValue('@PhilErrorTracking/layout.html.twig')->cannotBeEmpty()->end()
                        ->integerNode('page_size')->min(1)->max(500)->defaultValue(50)->end()
                        ->integerNode('events_shown')->min(1)->max(500)->defaultValue(10)->end()
                    ->end()
                ->end()
                ->arrayNode('browser')
                    ->info("The endpoint of the browsers' errors, for the Stimulus controller.")
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('max_bytes')->min(512)->max(262_144)->defaultValue(BrowserErrorReceiver::DEFAULT_MAX_BYTES)->end()
                        ->integerNode('max_frames')->min(1)->max(200)->defaultValue(BrowserErrorParser::DEFAULT_MAX_FRAMES)->end()
                        ->arrayNode('origins')
                            ->info("The origins allowed to post, https://example.com; the endpoint's own when empty.")
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('token')->info('A token the controller sends in X-Error-Tracking-Token, checked when set.')->defaultNull()->end()
                        ->arrayNode('rate_limit')
                            ->info('Reports a client may post, counted on a keyed digest of its address by the limiter "phil_error_tracking.browser" of phil/rate-limit-bundle, or by symfony/rate-limiter without that bundle.')
                            ->canBeDisabled()
                            ->children()
                                ->integerNode('limit')->min(1)->defaultValue(10)->end()
                                ->scalarNode('interval')->defaultValue('1 minute')->end()
                                ->scalarNode('cache_pool')->info('A pool on a shared store: one local to each server is refused in the prod environment.')->defaultValue('cache.app')->end()
                                ->booleanNode('single_server')->info('Accepts a pool local to the server in the prod environment, for an application on one server.')->defaultFalse()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('clock')->info('Service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $clock = service($this->string($config, 'clock'));
        $address = $this->section($config, 'client_address');
        $storage = $this->section($config, 'storage');
        $capture = $this->section($config, 'capture');
        $alerts = $this->section($config, 'alerts');

        $services->set('phil_error_tracking.ip_anonymizer', Truncation::class)->args([$address['ipv4_prefix'], $address['ipv6_prefix']]);
        $services->set(EventFactory::class)->args([
            $this->string($config, 'project_dir'),
            $clock,
            $config['release'],
            $config['environment'],
            $config['max_frames'],
            $config['max_chain'],
        ])->public();
        $fields = $this->strings($config, 'scrub_fields');
        $prefixes = $this->strings($config, 'token_path_prefixes');

        // Built once here so that a malformed prefix fails the container build rather than the first capture.
        try {
            new Scrubber($fields, $prefixes);
        } catch (InvalidOption $invalidOption) {
            throw new InvalidConfigurationException('phil_error_tracking.token_path_prefixes: '.$invalidOption->getMessage(), 0, $invalidOption);
        }

        $services->set(Scrubber::class)->args([$fields, $prefixes]);
        $services->set(Fingerprinter::class);
        $services->set(Sampler::class)->args([$config['sample_rate']]);

        $connection = $this->string($storage, 'connection');
        $dbal = service(\sprintf('doctrine.dbal.%s_connection', $connection));
        $services->set(DbalErrorStore::class)->args([$dbal, $storage['events_per_group']]);
        $services->alias(ErrorStore::class, DbalErrorStore::class)->public();
        $services->alias(ErrorGroupReader::class, DbalErrorStore::class)->public();

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $services->set(SchemaListener::class)->args([$dbal])->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $connection]);
        }

        $services->set(ErrorTracker::class)->args([
            service(EventFactory::class),
            service(ErrorStore::class),
            service(Scrubber::class),
            service(Fingerprinter::class),
            service(Sampler::class),
            $this->throttle($services, $builder, 'group', $this->section($config, 'rate_limit'), 'phil_error_tracking.rate_limit'),
            $this->bool($alerts, 'enabled') ? $this->alerter($services, $builder, $alerts, $config['environment'], $this->bool($this->section($config, 'admin'), 'enabled')) : null,
        ])->public();
        $services->set(SafeCapture::class)
            ->args([service(ErrorTracker::class), $this->exceptions($config), service('logger')->nullOnInvalid()])
            ->tag('kernel.reset', ['method' => 'reset'])
            ->public();
        $services->set(AlertDestination::class, AlertDestination::class)
            ->factory(AlertDestination::from(...))
            ->args([$this->destination($alerts)->value])
            ->public();
        $services->set(RequestSummaries::class)->args([service('phil_error_tracking.ip_anonymizer')]);

        $this->loadCapture($services, $capture);

        if (class_exists(Command::class)) {
            $services->set(PurgeCommand::class)->args([service(ErrorGroupReader::class), $clock, $storage['retention']])->tag('console.command');
            $services->set(StatusCommand::class)->args([service(ErrorGroupReader::class), service(AlertDestination::class), $this->capturePoints($capture, $this->section($config, 'browser'))])->tag('console.command');
        }

        $admin = $this->section($config, 'admin');

        if ($this->bool($admin, 'enabled') && class_exists(Environment::class) && interface_exists(AuthorizationCheckerInterface::class) && interface_exists(CsrfTokenManagerInterface::class)) {
            $controller = $services->set(AdminController::class)->args([
                service(ErrorGroupReader::class),
                service(ErrorStore::class),
                service('security.authorization_checker'),
                service('security.csrf.token_manager'),
                service('twig'),
                service('router'),
                service(AlertDestination::class),
                $this->string($admin, 'role'),
                $this->string($admin, 'layout'),
                $admin['page_size'],
                $admin['events_shown'],
            ])->public()->tag('controller.service_arguments');

            // The same pages grafted on every dashboard of an EasyAdmin back office, through #[AdminRoute].
            if (class_exists(EasyAdminBundle::class) && $this->isRegistered($builder, 'EasyAdminBundle')) {
                $controller->tag(EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER);
            }
        }

        $browser = $this->section($config, 'browser');

        if ($this->bool($browser, 'enabled')) {
            $this->loadBrowser($services, $builder, $browser);
        }

        $health = $this->section($config, 'health_check');

        if ($this->bool($health, 'enabled') && interface_exists(HealthCheck::class)) {
            $services->set(ErrorStoreCheck::class)
                ->args([service(ErrorGroupReader::class), $clock, $health['new_groups'], $health['window']])
                ->autoconfigure();
        }
    }

    /**
     * @param array<array-key, mixed> $capture
     */
    private function loadCapture(ServicesConfigurator $services, array $capture): void
    {
        $http = $this->section($capture, 'http');
        $messenger = $this->section($capture, 'messenger');

        if ($this->bool($http, 'enabled')) {
            $services->set(ExceptionListener::class)
                ->args([service(SafeCapture::class), service(RequestSummaries::class), $this->bool($http, 'client_errors')])
                ->tag('kernel.event_listener', ['event' => 'kernel.exception', 'method' => 'onKernelException', 'priority' => 128]);
        }

        if ($this->bool($messenger, 'enabled') && class_exists(WorkerMessageFailedEvent::class)) {
            $services->set(WorkerFailureListener::class)
                ->args([service(SafeCapture::class), $this->bool($messenger, 'retries')])
                ->tag('kernel.event_listener', ['event' => WorkerMessageFailedEvent::class, 'method' => 'onMessageFailed']);
        }

        if ($this->bool($capture, 'console') && class_exists(Command::class)) {
            $services->set(ConsoleErrorListener::class)
                ->args([service(SafeCapture::class)])
                ->tag('kernel.event_listener', ['event' => 'console.error', 'method' => 'onConsoleError']);
        }

        if (class_exists(AbstractProcessingHandler::class)) {
            $services->set(self::MONOLOG_HANDLER, ErrorTrackingHandler::class)
                ->args([service(SafeCapture::class), service(EventFactory::class), $this->string($capture, 'monolog_level')])
                ->public();
        }
    }

    /**
     * Where the alerts go as configured; `AlertsPass` makes it `None` when the chat they fall back on has no chatter.
     *
     * @param array<array-key, mixed> $alerts
     */
    private function destination(array $alerts): AlertDestination
    {
        if (!$this->bool($alerts, 'enabled')) {
            return AlertDestination::Disabled;
        }

        return [] !== $this->strings($alerts, 'recipients') || null !== ($alerts['audience'] ?? null) ? AlertDestination::Notification : AlertDestination::Chat;
    }

    /**
     * Whether each capture point is on, for `phil:error-tracking:status`.
     *
     * @param array<array-key, mixed> $capture
     * @param array<array-key, mixed> $browser
     *
     * @return array<string, bool>
     */
    private function capturePoints(array $capture, array $browser): array
    {
        return [
            'requests' => $this->bool($this->section($capture, 'http'), 'enabled'),
            'failed messages' => $this->bool($this->section($capture, 'messenger'), 'enabled') && class_exists(WorkerMessageFailedEvent::class),
            'commands' => $this->bool($capture, 'console'),
            'log records' => class_exists(AbstractProcessingHandler::class),
            "browsers' reports" => $this->bool($browser, 'enabled'),
        ];
    }

    /**
     * @param array<array-key, mixed> $alerts
     */
    private function alerter(ServicesConfigurator $services, ContainerBuilder $builder, array $alerts, mixed $environment, bool $admin): ReferenceConfigurator
    {
        $recipients = $this->strings($alerts, 'recipients');
        $audience = $alerts['audience'] ?? null;

        if ([] !== $recipients || null !== $audience) {
            return $this->notificationAlerter($services, $builder, $recipients, \is_string($audience) ? $audience : null, $admin);
        }

        $services->set(ChatterAlerter::class)->args([service('chatter'), $alerts['transport'], $environment]);

        if (!$this->bool($alerts, 'async') || !interface_exists(MessageBusInterface::class)) {
            return service(ChatterAlerter::class);
        }

        $services->set(MessengerAlerter::class)->args([service('messenger.default_bus')]);
        $services->set(AlertErrorGroupHandler::class)
            ->args([service(ErrorStore::class), service(ChatterAlerter::class)])
            ->tag('messenger.message_handler', ['handles' => AlertErrorGroup::class]);

        return service(MessengerAlerter::class);
    }

    /**
     * The alerter of phil/notifications-bundle, its recipients checked once by building the message they would get.
     *
     * @param list<string> $recipients
     */
    private function notificationAlerter(ServicesConfigurator $services, ContainerBuilder $builder, array $recipients, ?string $audience, bool $admin): ReferenceConfigurator
    {
        if ([] !== $recipients && null !== $audience) {
            throw new InvalidConfigurationException('phil_error_tracking.alerts: name recipients or an audience, not both.');
        }

        if (!class_exists(Notifications::class) || !$this->isRegistered($builder, 'PhilNotificationsBundle')) {
            throw new InvalidConfigurationException('phil_error_tracking.alerts: recipients and an audience are notified by phil/notifications-bundle: install it and register PhilNotificationsBundle.');
        }

        try {
            new SendNotification(NotificationAlerter::TYPE, $recipients, $audience);
        } catch (InvalidNotification $invalidNotification) {
            throw new InvalidConfigurationException('phil_error_tracking.alerts: '.$invalidNotification->getMessage(), $invalidNotification->getCode(), previous: $invalidNotification);
        }

        $services->set(NotificationAlerter::class)->args([service(Notifications::class), $recipients, $audience]);
        $services->set(ErrorGroupSubjects::class)
            ->args([service(ErrorStore::class), $admin ? service('router') : null])
            ->autoconfigure();

        return service(NotificationAlerter::class);
    }

    /**
     * @param array<array-key, mixed> $browser
     */
    private function loadBrowser(ServicesConfigurator $services, ContainerBuilder $builder, array $browser): void
    {
        $origins = [];

        foreach ($this->strings($browser, 'origins') as $origin) {
            $origins[] = $this->origin($origin);
        }

        $services->set(BrowserErrorParser::class)->args([$browser['max_frames']]);
        $services->set(BrowserErrorReceiver::class)->args([
            service(BrowserErrorParser::class),
            service(EventFactory::class),
            service(SafeCapture::class),
            service('phil_error_tracking.ip_anonymizer'),
            '%kernel.secret%',
            $browser['max_bytes'],
            $origins,
            $browser['token'],
            $this->throttle($services, $builder, 'browser', $this->section($browser, 'rate_limit'), 'phil_error_tracking.browser.rate_limit', self::BROWSER_LIMITER),
        ]);
        $services->set(BrowserErrorController::class)->args([service(BrowserErrorReceiver::class)])->public()->tag('controller.service_arguments');
    }

    /**
     * A throttle over a sliding window of symfony/rate-limiter in a cache pool, or none; through the named limiter of
     * phil/rate-limit-bundle when one is given and that bundle is registered.
     *
     * @param array<array-key, mixed> $rate
     */
    private function throttle(ServicesConfigurator $services, ContainerBuilder $builder, string $name, array $rate, string $path, ?string $limiter = null): ?ReferenceConfigurator
    {
        if (!$this->bool($rate, 'enabled')) {
            return null;
        }

        RateLimitGuard::requireComponent(interface_exists(RateLimiterFactoryInterface::class), $path);

        $pool = $this->string($rate, 'cache_pool');
        RateLimitGuard::watch($builder, $pool, $path.'.cache_pool', $this->bool($rate, 'single_server'), 'enabled');

        $id = 'phil_error_tracking.'.$name.'_throttle';
        $services->set($id.'.storage', CacheStorage::class)->args([service($pool)]);
        $services->set($id.'.limiter', RateLimiterFactory::class)->args([
            ['id' => 'phil_error_tracking_'.$name, 'policy' => 'sliding_window', 'limit' => $rate['limit'], 'interval' => $this->string($rate, 'interval')],
            service($id.'.storage'),
        ]);

        if (null !== $limiter && interface_exists(Limiter::class) && $this->isRegistered($builder, 'PhilRateLimitBundle')) {
            $services->set($id.'.limit', SymfonyLimiter::class)->args([service($id.'.limiter')])->tag('phil_rate_limit.limiter', ['name' => $limiter]);
            $services->set($id, LimiterCaptureThrottle::class)->args([service($id.'.limit'), service('request_stack')]);

            return service($id);
        }

        $services->set($id, RateLimiterThrottle::class)->args([service($id.'.limiter')]);

        return service($id);
    }

    /**
     * An origin of the configuration, read by `phil/url` and written as a browser sends it: a scheme, a host and a port,
     * with no path, query nor fragment.
     */
    private function origin(string $origin): string
    {
        try {
            // Nothing past the authority: no path, query, fragment nor space, which `Url` would drop or trim.
            $url = 1 === preg_match('#^https?://[^/?\#\\\\\s]+$#Di', $origin) ? Url::fromString($origin, allowLocalhost: true) : null;
        } catch (UrlException) {
            $url = null;
        }

        if (!$url instanceof Url) {
            throw new InvalidConfigurationException(\sprintf('phil_error_tracking.browser.origins: "%s" is not an origin such as https://example.com.', $origin));
        }

        return BrowserErrorParser::originOf($url);
    }

    private function isRegistered(ContainerBuilder $builder, string $bundle): bool
    {
        return $builder->hasParameter('kernel.bundles') && \array_key_exists($bundle, (array) $builder->getParameter('kernel.bundles'));
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return list<class-string<Throwable>>
     */
    private function exceptions(array $config): array
    {
        $classes = [];

        foreach ($this->strings($config, 'ignored_exceptions') as $class) {
            if (!is_a($class, Throwable::class, true)) {
                throw new InvalidConfigurationException(\sprintf('phil_error_tracking.ignored_exceptions: "%s" is not a throwable class.', $class));
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function section(array $config, string $key): array
    {
        return \is_array($config[$key] ?? null) ? $config[$key] : [];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function string(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!\is_string($value) || '' === $value) {
            throw new InvalidConfigurationException(\sprintf('phil_error_tracking: %s must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function bool(array $config, string $key): bool
    {
        return true === ($config[$key] ?? null);
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return list<string>
     */
    private function strings(array $config, string $key): array
    {
        return array_values(array_filter($this->section($config, $key), \is_string(...)));
    }
}
