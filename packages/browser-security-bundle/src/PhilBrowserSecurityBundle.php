<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Override;
use Phil\BrowserSecurityBundle\Application\BrowserSecurityContext;
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Application\CookieSigner;
use Phil\BrowserSecurityBundle\Application\HeaderBuilder;
use Phil\BrowserSecurityBundle\Application\PolicyRegistry;
use Phil\BrowserSecurityBundle\Application\ReportParser;
use Phil\BrowserSecurityBundle\Application\ReportReceiver;
use Phil\BrowserSecurityBundle\Application\SecurityHeaders;
use Phil\BrowserSecurityBundle\Application\UserContentPolicy;
use Phil\BrowserSecurityBundle\Domain\Contract\ReportRepository;
use Phil\BrowserSecurityBundle\Domain\Enum\ExternalRedirectAction;
use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\ProtectionSyntax;
use Phil\BrowserSecurityBundle\Infrastructure\Cache\CacheDeduplicator;
use Phil\BrowserSecurityBundle\Infrastructure\Console\PurgeReportsCommand;
use Phil\BrowserSecurityBundle\Infrastructure\Doctrine\DbalReportRepository;
use Phil\BrowserSecurityBundle\Infrastructure\Doctrine\ReportSchemaListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ContentSecurityPolicyListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ExternalRedirectListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\HttpsRedirectListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ReportController;
use Phil\BrowserSecurityBundle\Infrastructure\Http\ReportEndpoint;
use Phil\BrowserSecurityBundle\Infrastructure\Http\SecurityHeadersListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\SignedCookiesListener;
use Phil\BrowserSecurityBundle\Infrastructure\Http\UserContentListener;
use Phil\BrowserSecurityBundle\Infrastructure\IpAnonymizer\TruncatedClientAddress;
use Phil\BrowserSecurityBundle\Infrastructure\Profiler\CspDataCollector;
use Phil\BrowserSecurityBundle\Infrastructure\RateLimit\LimiterReportThrottle;
use Phil\BrowserSecurityBundle\Infrastructure\RateLimiter\RateLimiterThrottle;
use Phil\BrowserSecurityBundle\Infrastructure\Twig\CspExtension;
use Phil\BrowserSecurityBundle\Infrastructure\Twig\NoncedImportMapRuntime;
use Phil\IpAnonymizer\Truncation;
use Phil\RateLimit\RateLimitGuard;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use Symfony\Bridge\Twig\Extension\ImportMapRuntime;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Http\EventListener\ClearSiteDataLogoutListener;
use Twig\Extension\AbstractExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The protections a Symfony application asks of the browser — its content security policy, nonces and reports, the
 * headers beside it, HTTPS, signed cookies and external redirects —, wired from its configuration:
 *
 * ```yaml
 * phil_browser_security:
 *     policies:
 *         default:
 *             directives:
 *                 default-src: ["'self'"]
 *                 object-src: ["'none'"]
 *             strict_dynamic: true
 *         preview:
 *             directives: { default-src: ["'none'"], style-src: ["'unsafe-inline'"], sandbox: true }
 *     report_only: ~
 *     user_content:
 *         inline_types: [image/png, image/jpeg, text/plain]
 * ```
 *
 * The policies and the headers are built and checked with the container, so that a bad value fails the build rather
 * than a request; every page can adjust each protection, by a route default, a controller attribute or a service.
 * The headers are written on `kernel.response`; the state of each request lives in its attributes, so the services
 * keep nothing between requests and a worker needs no reset.
 */
final class PhilBrowserSecurityBundle extends AbstractBundle
{
    /**
     * The policy enforced when the configuration declares none: everything from the site itself, nothing embedded as
     * an object, no `<base>` elsewhere, forms posted to the site only, pages framed by the site only, requests upgraded
     * to HTTPS.
     */
    public const array DEFAULT_POLICY = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'"],
        'style-src' => ["'self'"],
        'img-src' => ["'self'", 'data:'],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'"],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'self'"],
        'upgrade-insecure-requests' => true,
    ];

    private const int YEAR = 31_536_000;

    /** Just after the router of Symfony (32): the route default is known, and the firewall (8) has read nothing. */
    private const int ROUTE_KNOWN = 31;

    private const int ROUTE_READ = 30;

    /** Before the listeners that write headers, so that they also go on a response put in place of a redirect. */
    private const int BEFORE_HEADERS = 16;

    /** After the external redirects and before the listeners that write headers, which keep what the policy of user content wrote. */
    private const int USER_CONTENT = 8;

    /** After the listener of the session (-1000), so that every cookie of the response is there. */
    private const int LAST = -1024;

    public const string IMPORTMAP_RUNTIME = 'phil_browser_security.importmap_runtime';

    /** The name of the limiter of the report endpoint among those of phil/rate-limit-bundle. */
    public const string REPORT_LIMITER = 'phil_browser_security.report';

    #[Override]
    protected string $extensionAlias = 'phil_browser_security';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RateLimitGuard());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('enforce')->info('The named policy sent as Content-Security-Policy, or none.')->defaultValue('default')->end()
                ->scalarNode('report_only')->info('The named policy sent as Content-Security-Policy-Report-Only, to try a stricter one on real traffic.')->defaultNull()->end()
                ->arrayNode('policies')
                    ->info('The named policies; without any, "default" is a strict policy of the site\'s own sources.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('extends')->info('A policy whose directives this one takes, replacing those it declares.')->defaultNull()->end()
                            ->arrayNode('directives')
                                ->info('Sources by directive; true declares a directive without value, such as upgrade-insecure-requests or sandbox.')
                                ->useAttributeAsKey('name')
                                ->normalizeKeys(false)
                                ->variablePrototype()->end()
                            ->end()
                            ->booleanNode('strict_dynamic')->info("Adds 'strict-dynamic' next to the nonces and hashes of script-src.")->defaultFalse()->end()
                            ->booleanNode('report')->info('Adds report-uri and report-to.')->defaultTrue()->end()
                            ->arrayNode('inline')
                                ->info('Inline code allowed by the SHA-256 of its exact content.')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->arrayNode('script')->scalarPrototype()->end()->end()
                                    ->arrayNode('style')->scalarPrototype()->end()->end()
                                ->end()
                            ->end()
                            ->arrayNode('trusted_types')
                                ->info("require-trusted-types-for 'script' and the Trusted Types policies the page may create.")
                                ->canBeEnabled()
                                ->children()
                                    ->arrayNode('policies')->scalarPrototype()->end()->end()
                                    ->booleanNode('allow_duplicates')->defaultFalse()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('exclude_paths')
                    ->info('Prefixes of the paths whose responses get no header.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['/_wdt', '/_profiler'])
                ->end()
                ->booleanNode('importmap')->info('Puts the script nonce on the tags of importmap().')->defaultTrue()->end()
                ->arrayNode('reporting')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('group')->info('The name of the endpoint in Reporting-Endpoints and report-to.')->defaultValue(HeaderBuilder::DEFAULT_GROUP)->end()
                        ->scalarNode('uri')->info('The address of the reports, absolute or a path of the site, instead of the route phil_csp_report.')->defaultNull()->end()
                        ->integerNode('max_bytes')->min(512)->max(1_048_576)->defaultValue(ReportReceiver::DEFAULT_MAX_BYTES)->end()
                        ->integerNode('max_reports')->info('Reports read from one body at most.')->min(1)->max(100)->defaultValue(ReportParser::DEFAULT_MAX_REPORTS)->end()
                        ->arrayNode('rate_limit')
                            ->info('Bodies a client may post, counted on a keyed digest of its address by the limiter "phil_browser_security.report" of phil/rate-limit-bundle, or by symfony/rate-limiter without that bundle.')
                            ->canBeDisabled()
                            ->children()
                                ->integerNode('limit')->min(1)->defaultValue(60)->end()
                                ->scalarNode('interval')->defaultValue('1 minute')->end()
                                ->scalarNode('cache_pool')->info('A pool on a shared store: one local to each server is refused in the prod environment.')->defaultValue('cache.app')->end()
                                ->scalarNode('limiter')->info('The service id of a rate limiter factory of the application, instead of the one built here.')->defaultNull()->end()
                                ->booleanNode('single_server')->info('Accepts a pool local to the server in the prod environment, for an application on one server.')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->arrayNode('deduplication')
                            ->info('The window during which a violation reported again dispatches no event.')
                            ->canBeDisabled()
                            ->children()
                                ->integerNode('window')->min(1)->defaultValue(CacheDeduplicator::DEFAULT_WINDOW)->end()
                                ->scalarNode('cache_pool')->defaultValue('cache.app')->end()
                            ->end()
                        ->end()
                        ->arrayNode('client_address')
                            ->info("The client's address truncated by phil/ip-anonymizer, when it is installed; never kept raw.")
                            ->canBeDisabled()
                            ->children()
                                ->integerNode('ipv4_prefix')->min(0)->max(32)->defaultValue(24)->end()
                                ->integerNode('ipv6_prefix')->min(0)->max(128)->defaultValue(48)->end()
                            ->end()
                        ->end()
                        ->arrayNode('storage')
                            ->info('The violations kept in the table phil_csp_report through DBAL.')
                            ->canBeEnabled()
                            ->children()
                                ->scalarNode('connection')->defaultValue('default')->end()
                                ->integerNode('capacity')->min(1)->defaultValue(DbalReportRepository::DEFAULT_CAPACITY)->end()
                                ->integerNode('retention')->info('Days without a report after which phil:csp:purge-reports removes a violation.')->min(0)->defaultValue(30)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('headers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('permissions_policy')
                            ->info('Origins by feature: [] gives feature=(), [self] gives feature=(self).')
                            ->useAttributeAsKey('feature')
                            ->normalizeKeys(false)
                            ->arrayPrototype()->scalarPrototype()->end()->end()
                        ->end()
                        ->scalarNode('cross_origin_opener_policy')->defaultNull()->end()
                        ->scalarNode('cross_origin_opener_policy_report_only')->defaultNull()->end()
                        ->scalarNode('cross_origin_embedder_policy')->defaultNull()->end()
                        ->scalarNode('cross_origin_embedder_policy_report_only')->defaultNull()->end()
                        ->scalarNode('cross_origin_resource_policy')->defaultValue('same-site')->end()
                        ->scalarNode('referrer_policy')->defaultValue('strict-origin-when-cross-origin')->end()
                        ->scalarNode('content_type_options')->info('nosniff, or null to send nothing.')->defaultValue('nosniff')->end()
                        ->scalarNode('permitted_cross_domain_policies')->defaultValue('none')->end()
                        ->scalarNode('origin_agent_cluster')->info('"?1" asks for an agent cluster per origin, "?0" for one per site.')->defaultValue('?1')->end()
                        ->arrayNode('frame_options')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('default')->info('DENY, SAMEORIGIN, or null to send nothing.')->defaultValue('SAMEORIGIN')->end()
                                ->arrayNode('paths')
                                    ->info('The value by prefix of a path, on the boundary of a segment; null sends nothing.')
                                    ->useAttributeAsKey('path')
                                    ->normalizeKeys(false)
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('integrity_policy')
                            ->info('Refuses the scripts without integrity metadata.')
                            ->canBeEnabled()
                            ->children()
                                ->arrayNode('blocked_destinations')->scalarPrototype()->end()->defaultValue(['script'])->end()
                            ->end()
                        ->end()
                        ->arrayNode('integrity_policy_report_only')
                            ->info('Reports the scripts without integrity metadata, and blocks nothing.')
                            ->canBeEnabled()
                            ->children()
                                ->arrayNode('blocked_destinations')->scalarPrototype()->end()->defaultValue(['script'])->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('https')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('redirect')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->info('Sends plain requests to HTTPS; null follows kernel.debug: on when it is off.')->defaultNull()->end()
                                ->enumNode('status')->values([301, 302, 307, 308])->defaultValue(308)->end()
                                ->integerNode('port')->min(1)->max(65535)->defaultValue(443)->end()
                                ->arrayNode('exclude_paths')->info('Prefixes of the paths served over both.')->scalarPrototype()->end()->end()
                            ->end()
                        ->end()
                        ->arrayNode('strict_transport_security')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')->info('Sends Strict-Transport-Security; null follows kernel.debug: on when it is off.')->defaultNull()->end()
                                ->integerNode('max_age')->min(0)->defaultValue(self::YEAR)->end()
                                ->booleanNode('include_subdomains')->defaultFalse()->end()
                                ->booleanNode('preload')->defaultFalse()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('signed_cookies')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('names')->info('The cookies signed on every page.')->scalarPrototype()->end()->end()
                        ->scalarNode('secret')->defaultValue('%kernel.secret%')->cannotBeEmpty()->end()
                        ->arrayNode('previous_secrets')->info('Secrets no longer used to sign and still accepted.')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
                ->arrayNode('external_redirects')
                    ->canBeDisabled()
                    ->children()
                        ->enumNode('action')->values(array_column(ExternalRedirectAction::cases(), 'value'))->defaultValue(ExternalRedirectAction::Refuse->value)->end()
                        ->scalarNode('fallback')->info('The path a refused redirect goes to with the action "fallback".')->defaultValue('/')->end()
                        ->arrayNode('allow')->info('Hosts redirects may go to, or domains written with a leading dot.')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
                ->arrayNode('clear_site_data')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('on_logout')
                            ->info("What the browser forgets when a user logs out of any firewall, with Symfony's listener; [] for nothing.")
                            ->scalarPrototype()->end()
                            ->defaultValue(['cookies', 'storage'])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('user_content')
                    ->info('The strict headers of a file someone uploaded, applied by the route default _user_content, #[UserContent] or UserContentPolicy::apply().')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->variableNode('directives')
                            ->info("Its own Content-Security-Policy, by directive as a named policy declares them: default-src 'none' and sandbox by default.")
                            ->defaultValue(UserContentPolicy::DEFAULT_DIRECTIVES)
                        ->end()
                        ->arrayNode('inline_types')
                            ->info('The media types shown in the browser; every other is served as an attachment, and HTML, SVG, XML and scripts always are.')
                            ->scalarPrototype()->end()
                            ->defaultValue(UserContentPolicy::DEFAULT_INLINE_TYPES)
                        ->end()
                        ->enumNode('cross_origin_resource_policy')->values(UserContentPolicy::RESOURCE_POLICIES)->defaultValue('same-origin')->end()
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
        $policies = $this->policies($config);
        $enforce = $this->name($config, 'enforce', $policies);
        $reportOnly = $this->name($config, 'report_only', $policies);
        $reporting = $this->section($config, 'reporting');
        $headers = $this->section($config, 'headers');
        $group = $this->token($reporting, 'group');

        $services->set(PolicyRegistry::class)->factory([PolicyRegistry::class, 'fromConfig'])->args([$policies]);
        $services->set(HeaderBuilder::class)->args([service(PolicyRegistry::class), $enforce, $reportOnly, $group])->public();
        $services->set(ContentSecurityPolicyContext::class)->args([service('request_stack')])->public();

        $services->set(BrowserSecurityContext::class)->args([service('request_stack')])->public();
        $services->set(ReportEndpoint::class)->args([service('router')->nullOnInvalid(), $this->bool($reporting, 'enabled'), $this->uri($reporting)]);

        $securityHeaders = [$this->headerValues($headers, $this->section($this->section($config, 'https'), 'strict_transport_security'), $builder), $this->framePaths($headers), $this->bool($reporting, 'enabled') ? $group : null];

        try {
            new SecurityHeaders(...$securityHeaders);
        } catch (InvalidPolicy $invalidPolicy) {
            throw new InvalidConfigurationException('phil_browser_security.headers: '.$invalidPolicy->getMessage(), $invalidPolicy->getCode(), previous: $invalidPolicy);
        }

        $excludedPaths = $this->strings($config, 'exclude_paths');
        $services->set(SecurityHeaders::class)->args($securityHeaders);
        $services->set(ContentSecurityPolicyListener::class)
            ->args([service(HeaderBuilder::class), service(ReportEndpoint::class), $excludedPaths])
            ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onKernelController'])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);
        $services->set(SecurityHeadersListener::class)
            ->args([service(SecurityHeaders::class), service(ReportEndpoint::class), $group, $excludedPaths])
            ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => self::ROUTE_KNOWN])
            ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onKernelController'])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);

        $this->loadUserContent($this->section($config, 'user_content'), $container);
        $this->loadHttps($this->section($this->section($config, 'https'), 'redirect'), $container, $builder);
        $this->loadCookies($this->section($config, 'signed_cookies'), $container);
        $this->loadExternalRedirects($this->section($config, 'external_redirects'), $excludedPaths, $container);
        $this->loadClearSiteData($this->section($config, 'clear_site_data'), $container);

        if ($this->bool($reporting, 'enabled')) {
            $this->loadReporting($reporting, $container, $builder, service($this->string($config, 'clock')));
        }

        if (class_exists(AbstractExtension::class)) {
            $services->set(CspExtension::class)->args([service(ContentSecurityPolicyContext::class)])->tag('twig.extension');
        }

        // The runtime of importmap() replaced by one that adds the nonce; without AssetMapper there is nothing to replace.
        // Twig finds a runtime by the class its service declares: the service declares the parent's, and a factory
        // builds the subclass.
        if ($this->bool($config, 'importmap') && class_exists(ImportMapRuntime::class)) {
            $services->set(self::IMPORTMAP_RUNTIME, ImportMapRuntime::class)
                ->decorate('twig.runtime.importmap', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
                ->factory([NoncedImportMapRuntime::class, 'create'])
                ->args([service('asset_mapper.importmap.renderer'), service(ContentSecurityPolicyContext::class)]);
        }

        // The profiler shows the policy of each response; without WebProfilerBundle nothing collects, and the service
        // is removed.
        $services->set(CspDataCollector::class)->tag('data_collector', ['template' => CspDataCollector::TEMPLATE, 'id' => 'phil_browser_security']);
    }

    /**
     * The policy of user content, checked here so that a bad directive or media type fails the build, and its listener.
     *
     * @param array<array-key, mixed> $userContent
     */
    private function loadUserContent(array $userContent, ContainerConfigurator $container): void
    {
        $directives = [];

        foreach ($this->section($userContent, 'directives') as $directive => $values) {
            $directives[(string) $directive] = match (true) {
                \is_bool($values) => $values,
                \is_string($values) => [$values],
                \is_array($values) => array_values($values),
                default => throw new InvalidConfigurationException(\sprintf('phil_browser_security.user_content.directives.%s: a list of sources, or true.', $directive)),
            };
        }

        /** @var array<string, list<string>|bool> $directives a source that is not a string is refused by the policy */
        $arguments = [$directives, $this->strings($userContent, 'inline_types'), $this->string($userContent, 'cross_origin_resource_policy', 'same-origin')];

        try {
            new UserContentPolicy(...$arguments);
        } catch (InvalidPolicy $invalidPolicy) {
            throw new InvalidConfigurationException('phil_browser_security.user_content: '.$invalidPolicy->getMessage(), $invalidPolicy->getCode(), previous: $invalidPolicy);
        }

        $services = $container->services();
        $services->set(UserContentPolicy::class)->args($arguments)->public();
        $services->set(UserContentListener::class)
            ->args([service(UserContentPolicy::class)])
            ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onKernelController'])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse', 'priority' => self::USER_CONTENT]);
    }

    /**
     * The value of each header, as `SecurityHeaders` takes them, without those the configuration leaves out.
     *
     * @param array<array-key, mixed> $headers
     * @param array<array-key, mixed> $transport
     *
     * @return array<string, mixed>
     */
    private function headerValues(array $headers, array $transport, ContainerBuilder $builder): array
    {
        $permissions = array_map(static fn (mixed $origins): array => \is_array($origins) ? array_values(array_filter($origins, is_string(...))) : [], $this->section($headers, 'permissions_policy'));
        $frame = $this->section($headers, 'frame_options');

        return array_filter([
            Protection::PermissionsPolicy->value => [] === $permissions ? null : $permissions,
            Protection::ReferrerPolicy->value => $this->optional($headers, 'referrer_policy'),
            Protection::FrameOptions->value => $this->optional($frame, 'default'),
            Protection::ContentTypeOptions->value => $this->optional($headers, 'content_type_options'),
            Protection::OpenerPolicy->value => $this->optional($headers, 'cross_origin_opener_policy'),
            Protection::OpenerPolicyReportOnly->value => $this->optional($headers, 'cross_origin_opener_policy_report_only'),
            Protection::EmbedderPolicy->value => $this->optional($headers, 'cross_origin_embedder_policy'),
            Protection::EmbedderPolicyReportOnly->value => $this->optional($headers, 'cross_origin_embedder_policy_report_only'),
            Protection::ResourcePolicy->value => $this->optional($headers, 'cross_origin_resource_policy'),
            Protection::PermittedCrossDomainPolicies->value => $this->optional($headers, 'permitted_cross_domain_policies'),
            Protection::OriginAgentCluster->value => $this->optional($headers, 'origin_agent_cluster'),
            Protection::IntegrityPolicy->value => $this->integrity($this->section($headers, 'integrity_policy')),
            Protection::IntegrityPolicyReportOnly->value => $this->integrity($this->section($headers, 'integrity_policy_report_only')),
            Protection::StrictTransportSecurity->value => $this->transport($transport, $builder),
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @param array<array-key, mixed> $integrity
     *
     * @return list<mixed>|null
     */
    private function integrity(array $integrity): ?array
    {
        return $this->bool($integrity, 'enabled') ? array_values($this->section($integrity, 'blocked_destinations')) : null;
    }

    /**
     * @param array<array-key, mixed> $transport
     */
    private function transport(array $transport, ContainerBuilder $builder): ?string
    {
        if (!$this->followsDebug($transport, $builder)) {
            return null;
        }

        $age = \is_int($transport['max_age'] ?? null) ? $transport['max_age'] : self::YEAR;
        $subdomains = $this->bool($transport, 'include_subdomains');
        $preload = $this->bool($transport, 'preload');

        if ($preload && (!$subdomains || $age < self::YEAR)) {
            throw new InvalidConfigurationException('phil_browser_security.https.strict_transport_security: "preload" needs "include_subdomains" and a "max_age" of one year or more.');
        }

        return 'max-age='.$age.($subdomains ? '; includeSubDomains' : '').($preload ? '; preload' : '');
    }

    /**
     * Whether a protection of HTTPS is on: as the configuration says, or when `kernel.debug` is off.
     *
     * @param array<array-key, mixed> $section
     */
    private function followsDebug(array $section, ContainerBuilder $builder): bool
    {
        return \is_bool($section['enabled'] ?? null) ? $section['enabled'] : false === $builder->getParameter('kernel.debug');
    }

    /**
     * @param array<array-key, mixed> $headers
     *
     * @return array<string, ?string>
     */
    private function framePaths(array $headers): array
    {
        $paths = [];

        foreach ($this->section($this->section($headers, 'frame_options'), 'paths') as $path => $value) {
            if (!str_starts_with((string) $path, '/') || (null !== $value && !\is_string($value))) {
                throw new InvalidConfigurationException(\sprintf('phil_browser_security.headers.frame_options.paths: "%s" is a path starting with "/", with a value or null.', $path));
            }

            $paths[(string) $path] = $value;
        }

        return $paths;
    }

    /**
     * @param array<array-key, mixed> $redirect
     */
    private function loadHttps(array $redirect, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->set(HttpsRedirectListener::class)
            ->args([$this->followsDebug($redirect, $builder), $this->strings($redirect, 'exclude_paths'), $redirect['status'], $redirect['port']])
            ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => self::ROUTE_READ]);
    }

    /**
     * @param array<array-key, mixed> $cookies
     */
    private function loadCookies(array $cookies, ContainerConfigurator $container): void
    {
        $services = $container->services();
        $names = $this->checked(Protection::SignedCookies, $this->strings($cookies, 'names'), 'signed_cookies.names');
        $services->set(CookieSigner::class)->args([$this->string($cookies, 'secret'), $this->strings($cookies, 'previous_secrets')]);
        $services->set(SignedCookiesListener::class)
            ->args([service(CookieSigner::class), $names])
            ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => self::ROUTE_READ])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse', 'priority' => self::LAST]);
    }

    /**
     * @param array<array-key, mixed> $redirects
     * @param list<string>            $excludedPaths
     */
    private function loadExternalRedirects(array $redirects, array $excludedPaths, ContainerConfigurator $container): void
    {
        if (!$this->bool($redirects, 'enabled')) {
            return;
        }

        $fallback = $this->string($redirects, 'fallback', '/');

        if (1 !== preg_match('~^/(?![/\\\\])[^\x00-\x20\x7F]*$~D', $fallback)) {
            throw new InvalidConfigurationException('phil_browser_security.external_redirects.fallback: a path of the site, starting with a single "/".');
        }

        $container->services()->set(ExternalRedirectListener::class)
            ->args([
                ExternalRedirectAction::from($this->string($redirects, 'action')),
                $this->checked(Protection::ExternalRedirects, $this->strings($redirects, 'allow'), 'external_redirects.allow'),
                $fallback,
                $excludedPaths,
                service('logger')->nullOnInvalid(),
            ])
            ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse', 'priority' => self::BEFORE_HEADERS]);
    }

    /**
     * Symfony's listener of the `clear_site_data` option of a firewall, for every firewall at once: Symfony hands a
     * listener of the global dispatcher the logout of each firewall.
     *
     * @param array<array-key, mixed> $clear
     */
    private function loadClearSiteData(array $clear, ContainerConfigurator $container): void
    {
        $directives = $this->checked(Protection::ClearSiteData, $this->strings($clear, 'on_logout'), 'clear_site_data.on_logout');

        if ([] !== $directives && class_exists(ClearSiteDataLogoutListener::class)) {
            $container->services()->set(ClearSiteDataLogoutListener::class)->args([$directives])->tag('kernel.event_subscriber');
        }
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function checked(Protection $protection, array $values, string $path): array
    {
        try {
            /** @var list<string> */
            return ProtectionSyntax::normalize($protection, $values);
        } catch (InvalidPolicy $invalidPolicy) {
            throw new InvalidConfigurationException(\sprintf('phil_browser_security.%s: %s', $path, $invalidPolicy->getMessage()), $invalidPolicy->getCode(), previous: $invalidPolicy);
        }
    }

    /**
     * @param array<array-key, mixed> $reporting
     */
    private function loadReporting(array $reporting, ContainerConfigurator $container, ContainerBuilder $builder, ReferenceConfigurator $clock): void
    {
        $services = $container->services();
        $rate = $this->section($reporting, 'rate_limit');
        $deduplication = $this->section($reporting, 'deduplication');
        $address = $this->section($reporting, 'client_address');
        $storage = $this->section($reporting, 'storage');
        $throttle = null;
        $deduplicator = null;
        $anonymizer = null;
        $repository = null;

        if ($this->bool($rate, 'enabled')) {
            $throttle = $this->loadThrottle($rate, $container, $builder);
        }

        if ($this->bool($deduplication, 'enabled')) {
            $services->set(CacheDeduplicator::class)->args([service($this->string($deduplication, 'cache_pool')), $deduplication['window']]);
            $deduplicator = service(CacheDeduplicator::class);
        }

        if ($this->bool($address, 'enabled') && class_exists(Truncation::class)) {
            $services->set('phil_browser_security.ip_truncation', Truncation::class)->args([$address['ipv4_prefix'], $address['ipv6_prefix']]);
            $services->set(TruncatedClientAddress::class)->args([service('phil_browser_security.ip_truncation')]);
            $anonymizer = service(TruncatedClientAddress::class);
        }

        if ($this->bool($storage, 'enabled')) {
            $repository = $this->loadStorage($storage, $container, $clock);
        }

        $services->set(ReportParser::class)->args([$reporting['max_reports']]);
        $services->set(ReportReceiver::class)->args([
            service(ReportParser::class),
            $clock,
            '%kernel.secret%',
            $reporting['max_bytes'],
            $throttle,
            $deduplicator,
            $anonymizer,
            $repository,
            service('event_dispatcher')->nullOnInvalid(),
        ])->public();
        $services->set(ReportController::class)->args([service(ReportReceiver::class)])->public()->tag('controller.service_arguments');
    }

    /**
     * The throttle of the report bodies, on a sliding window built here or the factory the configuration names: through
     * a named limiter when phil/rate-limit-bundle is registered, directly on `symfony/rate-limiter` otherwise.
     *
     * @param array<array-key, mixed> $rate
     */
    private function loadThrottle(array $rate, ContainerConfigurator $container, ContainerBuilder $builder): ReferenceConfigurator
    {
        $services = $container->services();
        RateLimitGuard::requireComponent(interface_exists(RateLimiterFactoryInterface::class), 'phil_browser_security.reporting.rate_limit');

        if (null === $rate['limiter']) {
            $pool = $this->string($rate, 'cache_pool');
            RateLimitGuard::watch($builder, $pool, 'phil_browser_security.reporting.rate_limit.cache_pool', $this->bool($rate, 'single_server'), 'enabled');
            $services->set('phil_browser_security.report_limiter.storage', CacheStorage::class)->args([service($pool)]);
            $services->set('phil_browser_security.report_limiter', RateLimiterFactory::class)->args([
                ['id' => 'phil_csp_report', 'policy' => 'sliding_window', 'limit' => $rate['limit'], 'interval' => $this->string($rate, 'interval')],
                service('phil_browser_security.report_limiter.storage'),
            ]);
        }

        $factory = service($this->string($rate, 'limiter', 'phil_browser_security.report_limiter'));

        if (!interface_exists(Limiter::class) || !$this->isRegistered($builder, 'PhilRateLimitBundle')) {
            $services->set(RateLimiterThrottle::class)->args([$factory]);

            return service(RateLimiterThrottle::class);
        }

        $services->set('phil_browser_security.report_limit', SymfonyLimiter::class)->args([$factory])->tag('phil_rate_limit.limiter', ['name' => self::REPORT_LIMITER]);
        $services->set(LimiterReportThrottle::class)->args([service('phil_browser_security.report_limit'), service('request_stack')]);

        return service(LimiterReportThrottle::class);
    }

    private function isRegistered(ContainerBuilder $builder, string $bundle): bool
    {
        return $builder->hasParameter('kernel.bundles') && \array_key_exists($bundle, (array) $builder->getParameter('kernel.bundles'));
    }

    /**
     * @param array<array-key, mixed> $storage
     */
    private function loadStorage(array $storage, ContainerConfigurator $container, ReferenceConfigurator $clock): ReferenceConfigurator
    {
        $services = $container->services();
        $connection = $this->string($storage, 'connection');
        $dbal = service(\sprintf('doctrine.dbal.%s_connection', $connection));
        $services->set(DbalReportRepository::class)->args([$dbal, $storage['capacity']]);
        $services->alias(ReportRepository::class, DbalReportRepository::class)->public();

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $services->set(ReportSchemaListener::class)
                ->args([$dbal])
                ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $connection]);
        }

        if (class_exists(Command::class)) {
            $services->set(PurgeReportsCommand::class)->args([service(ReportRepository::class), $clock, $storage['retention']])->tag('console.command');
        }

        return service(ReportRepository::class);
    }

    /**
     * The policies of the configuration, checked by building them once: a bad directive fails the container build.
     *
     * @param array<array-key, mixed> $config
     *
     * @return array<string, array<string, mixed>>
     */
    private function policies(array $config): array
    {
        $policies = [];

        foreach ($this->section($config, 'policies') as $name => $policy) {
            $policy = \is_array($policy) ? $policy : [];
            $directives = [];

            foreach ($this->section($policy, 'directives') as $directive => $values) {
                $directives[(string) $directive] = match (true) {
                    \is_bool($values) => $values,
                    \is_string($values) => [$values],
                    \is_array($values) => array_values($values),
                    default => throw new InvalidConfigurationException(\sprintf('phil_browser_security.policies.%s.directives.%s: a list of sources, or true.', $name, $directive)),
                };
            }

            $policies[(string) $name] = ['directives' => $directives] + $policy;
        }

        if ([] === $policies) {
            $policies['default'] = ['directives' => self::DEFAULT_POLICY];
        }

        try {
            /** @var array<string, array{extends?: ?string, directives?: array<string, list<string>|bool>, strict_dynamic?: bool, report?: bool, inline?: array{script?: list<string>, style?: list<string>}, trusted_types?: array{enabled?: bool, policies?: list<string>, allow_duplicates?: bool}}> $policies */
            PolicyRegistry::fromConfig($policies);
        } catch (InvalidPolicy $invalidPolicy) {
            throw new InvalidConfigurationException('phil_browser_security.policies: '.$invalidPolicy->getMessage(), $invalidPolicy->getCode(), previous: $invalidPolicy);
        }

        return $policies;
    }

    /**
     * @param array<array-key, mixed>             $config
     * @param array<string, array<string, mixed>> $policies
     */
    private function name(array $config, string $key, array $policies): ?string
    {
        $name = $config[$key] ?? null;

        if (null !== $name && (!\is_string($name) || !isset($policies[$name]))) {
            throw new InvalidConfigurationException(\sprintf('phil_browser_security.%s: no policy is named "%s".', $key, \is_string($name) ? $name : get_debug_type($name)));
        }

        return $name;
    }

    /**
     * @param array<array-key, mixed> $reporting
     */
    private function uri(array $reporting): ?string
    {
        $uri = $reporting['uri'] ?? null;

        if (null !== $uri && (!\is_string($uri) || 1 !== preg_match('#^(?:https://[A-Za-z0-9.-]+(?::[0-9]{1,5})?)?/[A-Za-z0-9._~!$&()*+=:@%/-]*$#D', $uri))) {
            throw new InvalidConfigurationException('phil_browser_security.reporting.uri: an https address or a path, without space, quote, comma nor semicolon.');
        }

        return $uri;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function token(array $config, string $key): string
    {
        $value = $this->string($config, $key, HeaderBuilder::DEFAULT_GROUP);

        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $value)) {
            throw new InvalidConfigurationException(\sprintf('phil_browser_security.reporting.%s: letters, digits, "-" and "_" only.', $key));
        }

        return $value;
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
    private function string(array $config, string $key, string $default = ''): string
    {
        $value = $config[$key] ?? $default;

        if (!\is_string($value) || '' === $value) {
            throw new InvalidConfigurationException(\sprintf('phil_browser_security: %s must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function optional(array $config, string $key): ?string
    {
        return null === ($config[$key] ?? null) ? null : $this->string($config, $key);
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
        return array_values(array_filter($this->section($config, $key), is_string(...)));
    }
}
