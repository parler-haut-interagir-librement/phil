<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle;

use Override;
use Phil\AnalyticsBundle\Application\Measure\ServerEventCounter;
use Phil\QrCode\ErrorCorrection;
use Phil\RateLimit\RateLimitGuard;
use Phil\RedirectsBundle\Application\LocationBuilder;
use Phil\RedirectsBundle\Application\TargetPolicy;
use Phil\SafeHttp\SafeHttpClient;
use Phil\ShortLinkBundle\Application\Code\CodeGenerator;
use Phil\ShortLinkBundle\Application\Code\DefaultBlocklist;
use Phil\ShortLinkBundle\Application\Code\SqidCodec;
use Phil\ShortLinkBundle\Application\Contract\VisitorClassifier;
use Phil\ShortLinkBundle\Application\Contract\VisitRecorder;
use Phil\ShortLinkBundle\Application\ShortLinkDomain;
use Phil\ShortLinkBundle\Application\ShortLinkDomains;
use Phil\ShortLinkBundle\Application\ShortLinkQrCodes;
use Phil\ShortLinkBundle\Application\ShortLinkResolver;
use Phil\ShortLinkBundle\Application\ShortLinks;
use Phil\ShortLinkBundle\Application\Visitor\KnownVisitorClassifier;
use Phil\ShortLinkBundle\Domain\Contract\ShortLinkRepository;
use Phil\ShortLinkBundle\Infrastructure\Analytics\AnalyticsVisitRecorder;
use Phil\ShortLinkBundle\Infrastructure\Doctrine\DoctrineShortLinkRepository;
use Phil\ShortLinkBundle\Infrastructure\Form\ShortLinkType;
use Phil\ShortLinkBundle\Infrastructure\Http\ShortLinkAdminController;
use Phil\ShortLinkBundle\Infrastructure\Http\ShortLinkListener;
use Phil\ShortLinkBundle\Infrastructure\RateLimiter\RateLimiterCreationThrottle;
use Phil\ShortLinkBundle\Infrastructure\SafeHttp\SafeHttpTargetCheck;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Short links on one or more dedicated domains — one per locale when needed —, with codes in the Sqids format, on the
 * resolver of phil/redirects-bundle: creation, validity dates, a maximum number of visits, the QR code of each link,
 * and each visit counted — the visits of people apart from those of robots, prefetches and link previews —, by
 * phil/analytics-bundle too when it is installed.
 *
 * ```yaml
 * phil_short_link:
 *     domains:
 *         - { host: go.example.com }
 *         - { host: lien.example.fr, locale: fr }
 *     targets:
 *         allowed_hosts: [example.com, example.fr]
 * ```
 *
 * The administration, the reachability check of a target and the analytics are wired when they are configured, and
 * fail the container build when their packages are missing. phil/redirects-bundle needs not be registered: its
 * location builder and its open-redirect guard are built here, with this configuration.
 */
final class PhilShortLinkBundle extends AbstractBundle
{
    public const string VISIT_RECORDER_TAG = 'phil_short_link.visit_recorder';

    public const string DEFAULT_LAYOUT = '@PhilShortLink/admin/layout.html.twig';

    /** Before the routing (32), so that a short link domain answers without a route. */
    public const int LISTENER_PRIORITY = 40;

    private const string LIMITER = 'phil_short_link.creation_limiter';

    #[Override]
    protected string $extensionAlias = 'phil_short_link';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RateLimitGuard());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info("Registers the bundle's services.")
                    ->defaultTrue()
                ->end()
                ->arrayNode('domains')
                    ->info('The domains that serve the short links, in order: a link is written on the first domain of its locale, or else on the first one without a locale.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('host')->info('A host name alone, without a port or a path.')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('locale')->info('The locale the domain serves; none for every locale.')->defaultNull()->end()
                            ->enumNode('scheme')->values(['https', 'http'])->defaultValue('https')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('home_url')
                    ->info('Where the root of a domain sends, an absolute URL; none answers it with a 404.')
                    ->defaultNull()
                ->end()
                ->booleanNode('allow_localhost')
                    ->info('Accepts "localhost" and the names under ".localhost" as domains and targets, in development and tests.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The service id of the Doctrine entity manager that keeps the links.')
                    ->defaultValue('doctrine.orm.entity_manager')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('code')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('alphabet')->info('The characters of the codes, each once: letters, digits, "-", ".", "_" and "~".')->defaultValue(SqidCodec::DEFAULT_ALPHABET)->end()
                        ->integerNode('min_length')->info('The shortest drawn code.')->min(0)->max(SqidCodec::MAX_LENGTH)->defaultValue(6)->end()
                        ->integerNode('max_number')->info('The largest number a drawn code writes: more numbers, fewer collisions, longer codes.')->min(1_000_000)->defaultValue(CodeGenerator::DEFAULT_MAX_NUMBER)->end()
                        ->arrayNode('blocklist')->info('Words a code avoids, added to the default list.')->scalarPrototype()->end()->end()
                        ->booleanNode('default_blocklist')->info('Keeps the default list of rude words in English and French.')->defaultTrue()->end()
                        ->booleanNode('custom')->info('Lets the author choose a code instead of drawing one.')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('targets')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('allowed_hosts')->info('The hosts a link may send to; none by default, so that no link leaves for a host the application did not name.')->scalarPrototype()->end()->end()
                        ->booleanNode('any_host')->info('Lets a link send to any host, the short link domains excepted.')->defaultFalse()->end()
                        ->booleanNode('check')->info('Reads each target through phil/safe-http before saving: refuses private networks and targets that do not answer.')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('rate_limit')
                    ->info('The links one author may create in a while.')
                    ->canBeDisabled()
                    ->children()
                        ->integerNode('limit')->min(1)->defaultValue(30)->end()
                        ->scalarNode('interval')->defaultValue('1 hour')->end()
                        ->scalarNode('cache_pool')->info('A pool on a shared store: one local to each server is refused in the prod environment.')->defaultValue('cache.app')->end()
                        ->scalarNode('limiter')->info('The service id of a rate limiter factory of the application, instead of the one built here.')->defaultNull()->end()
                        ->booleanNode('single_server')->info('Accepts a pool local to the server in the prod environment, for an application on one server.')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('qr_code')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('level')->values(['L', 'M', 'Q', 'H'])->defaultValue('M')->end()
                        ->integerNode('quiet_zone')->min(0)->max(16)->defaultValue(4)->end()
                        ->scalarNode('dark')->defaultValue('#000000')->end()
                        ->scalarNode('light')->defaultValue('#ffffff')->end()
                        ->integerNode('size')->info('The width and height of the SVG, in pixels.')->min(0)->max(4096)->defaultValue(256)->end()
                        ->integerNode('scale')->info('The pixels of a module in the PNG.')->min(1)->max(32)->defaultValue(8)->end()
                    ->end()
                ->end()
                ->arrayNode('admin')
                    ->info('The administration, mounted from the routes of the bundle behind the firewall; it needs Twig, the forms, the translator, the security and its CSRF tokens.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('role')->defaultValue('ROLE_ADMIN')->cannotBeEmpty()->end()
                        ->scalarNode('layout')->info('The template the pages extend; its "content" block holds them.')->defaultValue(self::DEFAULT_LAYOUT)->cannotBeEmpty()->end()
                        ->scalarNode('timezone')->info('The time zone the validity dates are written in, in the form.')->defaultValue('UTC')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('visitors')
                    ->info('How a visit is told from a machine: only the visits of people count towards the visits and the maximum of a link.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('link_previews')->info('Pieces of a User-Agent that name a link preview of a messaging service or a social network, added to the known ones.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                        ->arrayNode('robots')->info('Pieces of a User-Agent that name a crawler, a script or a monitor, added to the known ones.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                    ->end()
                ->end()
                ->arrayNode('analytics')
                    ->info('Counts each visit of a person as a server event of phil/analytics-bundle.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('site')->info('The analytics site the visits count for; none counts nothing.')->defaultNull()->end()
                        ->scalarNode('event')->info('The server event, declared in the "server_events" of the site.')->defaultValue('short_link_visit')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilShortLinkBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\ShortLinkBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('phil_short_link.enabled', true === $config['enabled']);

        if (true !== $config['enabled']) {
            return;
        }

        /** @var list<array{host: string, locale: ?string, scheme: string}> $domains */
        $domains = $config['domains'];

        if ([] === $domains) {
            throw new InvalidConfigurationException('phil_short_link.domains names at least one domain that serves the short links.');
        }

        /** @var array{alphabet: string, min_length: int, max_number: int, blocklist: list<string>, default_blocklist: bool, custom: bool} $code */
        $code = $config['code'];
        /** @var array{allowed_hosts: list<string>, any_host: bool, check: bool} $targets */
        $targets = $config['targets'];
        /** @var string $entityManager */
        $entityManager = $config['entity_manager'];
        $localhost = true === $config['allow_localhost'];
        $services = $container->services();
        $builder->registerForAutoconfiguration(VisitRecorder::class)->addTag(self::VISIT_RECORDER_TAG);

        $services->set(SqidCodec::class)->args([$code['alphabet'], $code['min_length'], [...($code['default_blocklist'] ? DefaultBlocklist::WORDS : []), ...$code['blocklist']]]);
        $services->set(CodeGenerator::class)->args([service(SqidCodec::class), $code['max_number']]);
        $services->set(ShortLinkDomains::class)->args([
            array_map(static fn (array $domain): Definition => new Definition(ShortLinkDomain::class, [$domain['host'], $domain['locale'], $domain['scheme']]), $domains),
            $localhost,
        ]);
        $services->set(DoctrineShortLinkRepository::class)->args([service($entityManager)]);
        $services->alias(ShortLinkRepository::class, DoctrineShortLinkRepository::class)->public();
        $services->set('phil_short_link.location_builder', LocationBuilder::class)->args([service('router')->nullOnInvalid(), $localhost]);
        $services->set('phil_short_link.target_policy', TargetPolicy::class)->args([$targets['allowed_hosts'], $localhost]);

        $services->set(ShortLinks::class)
            ->args([
                service(ShortLinkRepository::class),
                service(ShortLinkDomains::class),
                service(SqidCodec::class),
                service(CodeGenerator::class),
                service('phil_short_link.target_policy'),
                service('clock'),
                $targets['any_host'],
                $code['custom'],
                $localhost,
                $this->loadCheck($targets['check'], $container),
                $this->loadThrottle($this->section($config, 'rate_limit'), $container, $builder),
            ])
            ->public();

        $services->set(ShortLinkResolver::class)
            ->args([
                service(ShortLinkRepository::class),
                service(ShortLinkDomains::class),
                service(SqidCodec::class),
                service('phil_short_link.location_builder'),
                service('clock'),
                $this->homeUrl($config['home_url']),
                $localhost,
            ])
            ->public();

        /** @var array{level: string, quiet_zone: int, dark: string, light: string, size: int, scale: int} $qr */
        $qr = $config['qr_code'];
        $services->set(ShortLinkQrCodes::class)
            ->args([service(ShortLinks::class), ErrorCorrection::from($qr['level']), $qr['quiet_zone'], $qr['dark'], $qr['light'], $qr['size'], $qr['scale']])
            ->public();

        /** @var array{link_previews: list<string>, robots: list<string>} $visitors */
        $visitors = $config['visitors'];
        $services->set(KnownVisitorClassifier::class)->args([$visitors['link_previews'], $visitors['robots']]);
        $services->alias(VisitorClassifier::class, KnownVisitorClassifier::class);

        $services->set(ShortLinkListener::class)
            ->args([service(ShortLinkResolver::class), tagged_iterator(self::VISIT_RECORDER_TAG), service('logger')->nullOnInvalid(), service(VisitorClassifier::class)])
            ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => self::LISTENER_PRIORITY])
            ->tag('kernel.event_listener', ['event' => 'kernel.terminate', 'method' => 'onKernelTerminate'])
            ->tag('kernel.reset', ['method' => 'reset']);

        $this->loadAnalytics($this->section($config, 'analytics'), $container);
        $this->loadAdmin($this->section($config, 'admin'), $code['custom'], $container);
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function section(array $config, string $name): array
    {
        $section = $config[$name] ?? [];

        return \is_array($section) ? $section : [];
    }

    private function homeUrl(mixed $url): ?string
    {
        if (null === $url || '' === $url) {
            return null;
        }

        if (!\is_string($url) || 1 !== preg_match('#^https?://[^\s\\\\/?\#]+(?:[/?\#]\S*)?$#Di', $url)) {
            throw new InvalidConfigurationException('phil_short_link.home_url is an absolute http or https URL.');
        }

        return $url;
    }

    private function loadCheck(bool $check, ContainerConfigurator $container): ?ReferenceConfigurator
    {
        if (!$check) {
            return null;
        }

        if (!class_exists(SafeHttpClient::class)) {
            throw new InvalidConfigurationException('phil_short_link.targets.check needs phil/safe-http: install it, or set "check" to false.');
        }

        $services = $container->services();
        $services->set('phil_short_link.safe_http_client', SafeHttpClient::class)->args([service('http_client')->nullOnInvalid()])->tag('kernel.reset', ['method' => 'reset']);
        $services->set(SafeHttpTargetCheck::class)->args([service('phil_short_link.safe_http_client')]);

        return service(SafeHttpTargetCheck::class);
    }

    /**
     * The allowance of creations, on a sliding window built here or the factory the configuration names.
     *
     * @param array<array-key, mixed> $rate
     */
    private function loadThrottle(array $rate, ContainerConfigurator $container, ContainerBuilder $builder): ?ReferenceConfigurator
    {
        if (true !== ($rate['enabled'] ?? false)) {
            return null;
        }

        RateLimitGuard::requireComponent(interface_exists(RateLimiterFactoryInterface::class), 'phil_short_link.rate_limit');
        $services = $container->services();
        $limiter = $rate['limiter'] ?? null;

        if (!\is_string($limiter) || '' === $limiter) {
            $pool = \is_string($rate['cache_pool'] ?? null) ? $rate['cache_pool'] : 'cache.app';
            RateLimitGuard::watch($builder, $pool, 'phil_short_link.rate_limit.cache_pool', true === ($rate['single_server'] ?? false), 'enabled');
            $services->set(self::LIMITER.'.storage', CacheStorage::class)->args([service($pool)]);
            $services->set(self::LIMITER, RateLimiterFactory::class)->args([
                ['id' => 'phil_short_link_creation', 'policy' => 'sliding_window', 'limit' => $rate['limit'] ?? 30, 'interval' => $rate['interval'] ?? '1 hour'],
                service(self::LIMITER.'.storage'),
            ]);
            $limiter = self::LIMITER;
        }

        $services->set(RateLimiterCreationThrottle::class)->args([service($limiter)]);

        return service(RateLimiterCreationThrottle::class);
    }

    /**
     * @param array<array-key, mixed> $analytics
     */
    private function loadAnalytics(array $analytics, ContainerConfigurator $container): void
    {
        $site = $analytics['site'] ?? null;

        if (!\is_string($site) || '' === $site) {
            return;
        }

        if (!class_exists(ServerEventCounter::class)) {
            throw new InvalidConfigurationException('phil_short_link.analytics.site needs phil/analytics-bundle: install it, or leave "site" empty.');
        }

        $container->services()->set(AnalyticsVisitRecorder::class)
            ->args([service(ServerEventCounter::class), $site, $analytics['event'] ?? 'short_link_visit'])
            ->tag(self::VISIT_RECORDER_TAG);
    }

    /**
     * @param array<array-key, mixed> $admin
     */
    private function loadAdmin(array $admin, bool $customCodes, ContainerConfigurator $container): void
    {
        if (true !== ($admin['enabled'] ?? false)) {
            return;
        }

        if (!class_exists(Environment::class) || !interface_exists(FormFactoryInterface::class) || !interface_exists(CsrfTokenManagerInterface::class)) {
            throw new InvalidConfigurationException('phil_short_link.admin needs twig/twig, symfony/form and symfony/security-csrf: install them, or set "admin.enabled" to false.');
        }

        $services = $container->services();
        $services->set(ShortLinkType::class)->args([service(ShortLinkDomains::class), $customCodes, $admin['timezone'] ?? 'UTC'])->tag('form.type');
        $services->set(ShortLinkAdminController::class)
            ->args([
                service(ShortLinks::class),
                service(ShortLinkRepository::class),
                service(ShortLinkQrCodes::class),
                service('form.factory'),
                service('security.authorization_checker'),
                service('security.csrf.token_manager'),
                service('twig'),
                service('router'),
                service('translator'),
                service('clock'),
                $admin['role'] ?? 'ROLE_ADMIN',
                $admin['layout'] ?? self::DEFAULT_LAYOUT,
                service('security.token_storage')->nullOnInvalid(),
            ])
            ->public()
            ->tag('controller.service_arguments');
    }
}
