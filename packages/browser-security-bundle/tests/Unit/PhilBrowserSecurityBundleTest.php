<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit;

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
use Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle;
use Phil\IpAnonymizer\Truncation;
use Phil\RateLimit\RateLimitGuard;
use Phil\RateLimitBundle\Infrastructure\RateLimiter\SymfonyLimiter;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\ImportMapRuntime;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Http\EventListener\ClearSiteDataLogoutListener;

/**
 * The wiring the bundle declares, read on the `ContainerBuilder`.
 *
 * @phpstan-import-type PolicyConfig from PolicyRegistry
 */
#[CoversClass(PhilBrowserSecurityBundle::class)]
final class PhilBrowserSecurityBundleTest extends TestCase
{
    public function testTheBundleGuardsItsRateLimits(): void
    {
        $container = new ContainerBuilder();
        new PhilBrowserSecurityBundle()->build($container);

        self::assertTrue(array_any($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof RateLimitGuard));
    }

    public function testTheReportsFallBackOnTheRateLimiterComponent(): void
    {
        $container = $this->load([], ['PhilBrowserSecurityBundle' => PhilBrowserSecurityBundle::class]);

        self::assertTrue($container->hasDefinition(RateLimiterThrottle::class));
        self::assertFalse($container->hasDefinition(LimiterReportThrottle::class));
        self::assertSame(['phil_browser_security.reporting.rate_limit.cache_pool' => ['store' => 'cache.app', 'switch' => 'enabled']], RateLimitGuard::watched($container));
    }

    public function testTheReportsGoThroughANamedLimiterOfTheRateLimitBundle(): void
    {
        $container = $this->load(['reporting' => ['rate_limit' => ['limit' => 5]]], ['PhilRateLimitBundle' => PhilRateLimitBundle::class]);

        self::assertFalse($container->hasDefinition(RateLimiterThrottle::class));
        self::assertEquals(new Reference(LimiterReportThrottle::class), $container->getDefinition(ReportReceiver::class)->getArgument(4));
        self::assertEquals([new Reference('phil_browser_security.report_limit'), new Reference('request_stack')], $container->getDefinition(LimiterReportThrottle::class)->getArguments());
        $limiter = $container->getDefinition('phil_browser_security.report_limit');
        self::assertSame(SymfonyLimiter::class, $limiter->getClass());
        self::assertEquals([new Reference('phil_browser_security.report_limiter')], $limiter->getArguments());
        self::assertSame([['name' => 'phil_browser_security.report']], $limiter->getTag('phil_rate_limit.limiter'));
        self::assertSame(['id' => 'phil_csp_report', 'policy' => 'sliding_window', 'limit' => 5, 'interval' => '1 minute'], $container->getDefinition('phil_browser_security.report_limiter')->getArgument(0));
    }

    public function testAFactoryOfTheApplicationIsNotAWatchedPool(): void
    {
        $container = $this->load(['reporting' => ['rate_limit' => ['limiter' => 'limiter.csp']]], ['PhilRateLimitBundle' => PhilRateLimitBundle::class]);

        self::assertFalse($container->hasParameter(RateLimitGuard::STORES_PARAMETER));
        self::assertEquals([new Reference('limiter.csp')], $container->getDefinition('phil_browser_security.report_limit')->getArguments());
    }

    public function testTheExtensionAliasIsPhilBrowserSecurity(): void
    {
        self::assertSame('phil_browser_security', new PhilBrowserSecurityBundle()->getContainerExtension()?->getAlias());
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        $registry = $container->getDefinition(PolicyRegistry::class);
        self::assertSame([PolicyRegistry::class, 'fromConfig'], $registry->getFactory());
        self::assertSame([['default' => ['directives' => PhilBrowserSecurityBundle::DEFAULT_POLICY]]], $registry->getArguments());

        $builder = $container->getDefinition(HeaderBuilder::class);
        self::assertEquals([new Reference(PolicyRegistry::class), 'default', null, 'csp'], $builder->getArguments());
        self::assertTrue($builder->isPublic());
        self::assertEquals([new Reference('request_stack')], $container->getDefinition(ContentSecurityPolicyContext::class)->getArguments());
        self::assertTrue($container->getDefinition(ContentSecurityPolicyContext::class)->isPublic());
        self::assertSame([[
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-site',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Origin-Agent-Cluster' => '?1',
            'Strict-Transport-Security' => 'max-age=31536000',
        ], [], 'csp'], $container->getDefinition(SecurityHeaders::class)->getArguments());

        $listener = $container->getDefinition(ContentSecurityPolicyListener::class);
        self::assertEquals([new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), true, null], $container->getDefinition(ReportEndpoint::class)->getArguments());
        self::assertEquals([
            new Reference(HeaderBuilder::class),
            new Reference(ReportEndpoint::class),
            ['/_wdt', '/_profiler'],
        ], $listener->getArguments());
        self::assertSame([
            'kernel.event_listener' => [
                ['event' => 'kernel.controller', 'method' => 'onKernelController'],
                ['event' => 'kernel.response', 'method' => 'onKernelResponse'],
            ],
        ], $listener->getTags());

        self::assertEquals([ReportParser::DEFAULT_MAX_REPORTS], $container->getDefinition(ReportParser::class)->getArguments());
        $receiver = $container->getDefinition(ReportReceiver::class);
        self::assertEquals([
            new Reference(ReportParser::class),
            new Reference('clock'),
            '%kernel.secret%',
            ReportReceiver::DEFAULT_MAX_BYTES,
            new Reference(RateLimiterThrottle::class),
            new Reference(CacheDeduplicator::class),
            new Reference(TruncatedClientAddress::class),
            null,
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $receiver->getArguments());
        self::assertTrue($receiver->isPublic());

        self::assertEquals([new Reference('cache.app')], $container->getDefinition('phil_browser_security.report_limiter.storage')->getArguments());
        self::assertSame(CacheStorage::class, $container->getDefinition('phil_browser_security.report_limiter.storage')->getClass());
        self::assertSame(RateLimiterFactory::class, $container->getDefinition('phil_browser_security.report_limiter')->getClass());
        self::assertEquals([
            ['id' => 'phil_csp_report', 'policy' => 'sliding_window', 'limit' => 60, 'interval' => '1 minute'],
            new Reference('phil_browser_security.report_limiter.storage'),
        ], $container->getDefinition('phil_browser_security.report_limiter')->getArguments());
        self::assertEquals([new Reference('phil_browser_security.report_limiter')], $container->getDefinition(RateLimiterThrottle::class)->getArguments());
        self::assertEquals([new Reference('cache.app'), CacheDeduplicator::DEFAULT_WINDOW], $container->getDefinition(CacheDeduplicator::class)->getArguments());
        self::assertSame(Truncation::class, $container->getDefinition('phil_browser_security.ip_truncation')->getClass());
        self::assertSame([24, 48], $container->getDefinition('phil_browser_security.ip_truncation')->getArguments());
        self::assertEquals([new Reference('phil_browser_security.ip_truncation')], $container->getDefinition(TruncatedClientAddress::class)->getArguments());

        $controller = $container->getDefinition(ReportController::class);
        self::assertEquals([new Reference(ReportReceiver::class)], $controller->getArguments());
        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));

        self::assertFalse($container->hasDefinition(DbalReportRepository::class));
        self::assertFalse($container->hasAlias(ReportRepository::class));
        self::assertFalse($container->hasDefinition(PurgeReportsCommand::class));

        self::assertEquals([new Reference(ContentSecurityPolicyContext::class)], $container->getDefinition(CspExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(CspExtension::class)->hasTag('twig.extension'));

        $importmap = $container->getDefinition(PhilBrowserSecurityBundle::IMPORTMAP_RUNTIME);
        self::assertSame(ImportMapRuntime::class, $importmap->getClass());
        self::assertSame([NoncedImportMapRuntime::class, 'create'], $importmap->getFactory());
        self::assertSame(['twig.runtime.importmap', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE], $importmap->getDecoratedService());
        self::assertEquals([new Reference('asset_mapper.importmap.renderer'), new Reference(ContentSecurityPolicyContext::class)], $importmap->getArguments());

        self::assertSame([['template' => CspDataCollector::TEMPLATE, 'id' => 'phil_browser_security']], $container->getDefinition(CspDataCollector::class)->getTag('data_collector'));
    }

    public function testThePoliciesOfTheConfiguration(): void
    {
        $container = $this->load([
            'enforce' => 'site',
            'report_only' => 'strict',
            'policies' => [
                'site' => ['directives' => ['default-src' => "'self'", 'img-src' => ['a' => "'self'", 'b' => 'data:'], 'upgrade-insecure-requests' => true], 'strict_dynamic' => true],
                'strict' => ['extends' => 'site', 'directives' => ['script-src' => []], 'report' => false],
            ],
            'exclude_paths' => [3, '/_debug'],
            'importmap' => false,
            'headers' => [
                'permissions_policy' => ['camera' => [], 'display-capture' => [3, 'self']],
                'cross_origin_opener_policy' => 'same-origin',
                'cross_origin_embedder_policy' => 'credentialless',
                'referrer_policy' => null,
            ],
            'clock' => 'app.clock',
        ]);

        /** @var array<string, PolicyConfig> $policies */
        $policies = $container->getDefinition(PolicyRegistry::class)->getArgument(0);
        self::assertSame(['default-src' => ["'self'"], 'img-src' => ["'self'", 'data:'], 'upgrade-insecure-requests' => true], $policies['site']['directives'] ?? null);
        self::assertTrue($policies['site']['strict_dynamic'] ?? null);
        self::assertSame('site', $policies['strict']['extends'] ?? null);
        self::assertFalse($policies['strict']['report'] ?? null);
        self::assertEquals([new Reference(PolicyRegistry::class), 'site', 'strict', 'csp'], $container->getDefinition(HeaderBuilder::class)->getArguments());
        self::assertSame("default-src 'self'; img-src 'self' data:; upgrade-insecure-requests; script-src 'none'", PolicyRegistry::fromConfig($policies)->get('strict')->policy->header());
        self::assertSame(['/_debug'], $container->getDefinition(ContentSecurityPolicyListener::class)->getArgument(2));
        self::assertFalse($container->hasDefinition(PhilBrowserSecurityBundle::IMPORTMAP_RUNTIME));
        /** @var array{array<string, mixed>, array<string, ?string>, ?string} $headers */
        $headers = $container->getDefinition(SecurityHeaders::class)->getArguments();
        self::assertSame(['camera' => [], 'display-capture' => ['self']], $headers[0]['Permissions-Policy']);
        self::assertSame('same-origin', $headers[0]['Cross-Origin-Opener-Policy']);
        self::assertSame('credentialless', $headers[0]['Cross-Origin-Embedder-Policy']);
        self::assertArrayNotHasKey('Referrer-Policy', $headers[0]);
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(ReportReceiver::class)->getArgument(1));
    }

    public function testAPolicyNameKeepsItsHyphens(): void
    {
        $container = $this->load(['enforce' => 'site-wide', 'policies' => ['site-wide' => ['directives' => ['default-src' => ["'self'"]]]]]);

        self::assertSame('site-wide', $container->getDefinition(HeaderBuilder::class)->getArgument(1));
    }

    public function testTheRetentionOfTheStoreByDefault(): void
    {
        $container = $this->load(['reporting' => ['storage' => true]]);

        self::assertSame(30, $container->getDefinition(PurgeReportsCommand::class)->getArgument(2));
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), DbalReportRepository::DEFAULT_CAPACITY], $container->getDefinition(DbalReportRepository::class)->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function bounds(): iterable
    {
        foreach ([
            'max_bytes' => [[511, false], [512, true], [1_048_576, true], [1_048_577, false]],
            'max_reports' => [[0, false], [1, true], [100, true], [101, false]],
        ] as $option => $cases) {
            foreach ($cases as [$value, $accepted]) {
                yield $option.' '.$value => [['reporting' => [$option => $value]], $accepted];
            }
        }

        foreach ([
            'rate_limit' => ['limit' => [[0, false], [1, true]]],
            'deduplication' => ['window' => [[0, false], [1, true]]],
            'client_address' => ['ipv4_prefix' => [[-1, false], [0, true], [32, true], [33, false]], 'ipv6_prefix' => [[-1, false], [0, true], [128, true], [129, false]]],
            'storage' => ['capacity' => [[0, false], [1, true]], 'retention' => [[-1, false], [0, true]]],
        ] as $section => $options) {
            foreach ($options as $option => $cases) {
                foreach ($cases as [$value, $accepted]) {
                    yield $section.'.'.$option.' '.$value => [['reporting' => [$section => [$option => $value]]], $accepted];
                }
            }
        }

        foreach ([
            'redirect' => [
                'port' => [[0, false], [1, true], [65_535, true], [65_536, false]],
                'status' => [[301, true], [302, true], [303, false], [306, false], [307, true], [308, true], [309, false]],
            ],
            'strict_transport_security' => ['max_age' => [[-1, false], [0, true]]],
        ] as $section => $options) {
            foreach ($options as $option => $cases) {
                foreach ($cases as [$value, $accepted]) {
                    yield 'https.'.$section.'.'.$option.' '.$value => [['https' => [$section => [$option => $value]]], $accepted];
                }
            }
        }
    }

    public function testThePolicyOfUserContent(): void
    {
        $container = $this->load([]);

        self::assertSame([UserContentPolicy::DEFAULT_DIRECTIVES, UserContentPolicy::DEFAULT_INLINE_TYPES, 'same-origin'], $container->getDefinition(UserContentPolicy::class)->getArguments());
        self::assertTrue($container->getDefinition(UserContentPolicy::class)->isPublic());
        $listener = $container->getDefinition(UserContentListener::class);
        self::assertEquals([new Reference(UserContentPolicy::class)], $listener->getArguments());
        self::assertSame([
            ['event' => 'kernel.controller', 'method' => 'onKernelController'],
            ['event' => 'kernel.response', 'method' => 'onKernelResponse', 'priority' => 8],
        ], $listener->getTag('kernel.event_listener'));

        $configured = $this->load(['user_content' => ['directives' => ['default-src' => "'none'", 'sandbox' => true, 'img-src' => ["'self'"]], 'inline_types' => ['application/pdf'], 'cross_origin_resource_policy' => 'same-site']]);
        self::assertSame([['default-src' => ["'none'"], 'sandbox' => true, 'img-src' => ["'self'"]], ['application/pdf'], 'same-site'], $configured->getDefinition(UserContentPolicy::class)->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidUserContent(): iterable
    {
        yield 'directive' => [['directives' => ['nope' => true]], 'phil_browser_security.user_content: "nope" is not a directive'];
        yield 'value' => [['directives' => ['default-src' => 3]], 'phil_browser_security.user_content.directives.default-src: a list of sources, or true.'];
        yield 'source' => [['directives' => ['default-src' => [3]]], 'phil_browser_security.user_content: A value of "default-src" is not a string.'];
        yield 'active type' => [['inline_types' => ['text/html']], 'phil_browser_security.user_content: "text/html" can run as a page or a script'];
        yield 'resource policy' => [['cross_origin_resource_policy' => 'anyone'], 'The value "anyone" is not allowed'];
    }

    /**
     * @param array<string, mixed> $userContent
     */
    #[DataProvider('invalidUserContent')]
    public function testAnInvalidPolicyOfUserContentStopsTheBuild(array $userContent, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load(['user_content' => $userContent]);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('bounds')]
    public function testTheBoundsOfTheNumbers(array $config, bool $accepted): void
    {
        if (!$accepted) {
            $this->expectException(InvalidConfigurationException::class);
        }

        self::assertInstanceOf(ContainerBuilder::class, $this->load($config));
    }

    public function testNoPolicyEnforced(): void
    {
        $container = $this->load(['enforce' => null, 'report_only' => 'default']);

        self::assertEquals([new Reference(PolicyRegistry::class), null, 'default', 'csp'], $container->getDefinition(HeaderBuilder::class)->getArguments());
    }

    public function testTheReportingOptions(): void
    {
        $container = $this->load(['reporting' => [
            'group' => 'violations',
            'uri' => 'https://reports.example.com:8443/csp',
            'max_bytes' => 4096,
            'max_reports' => 5,
            'rate_limit' => ['limiter' => 'limiter.csp'],
            'deduplication' => ['window' => 60, 'cache_pool' => 'cache.reports'],
            'client_address' => ['ipv4_prefix' => 16, 'ipv6_prefix' => 32],
            'storage' => ['enabled' => true, 'connection' => 'logs', 'capacity' => 50, 'retention' => 7],
        ]]);

        self::assertSame('violations', $container->getDefinition(HeaderBuilder::class)->getArgument(3));
        self::assertSame('https://reports.example.com:8443/csp', $container->getDefinition(ReportEndpoint::class)->getArgument(2));
        self::assertSame(4096, $container->getDefinition(ReportReceiver::class)->getArgument(3));
        self::assertEquals([5], $container->getDefinition(ReportParser::class)->getArguments());
        self::assertFalse($container->hasDefinition('phil_browser_security.report_limiter'));
        self::assertEquals([new Reference('limiter.csp')], $container->getDefinition(RateLimiterThrottle::class)->getArguments());
        self::assertEquals([new Reference('cache.reports'), 60], $container->getDefinition(CacheDeduplicator::class)->getArguments());
        self::assertSame([16, 32], $container->getDefinition('phil_browser_security.ip_truncation')->getArguments());

        self::assertEquals([new Reference('doctrine.dbal.logs_connection'), 50], $container->getDefinition(DbalReportRepository::class)->getArguments());
        self::assertSame(DbalReportRepository::class, (string) $container->getAlias(ReportRepository::class));
        self::assertTrue($container->getAlias(ReportRepository::class)->isPublic());
        self::assertEquals(new Reference(ReportRepository::class), $container->getDefinition(ReportReceiver::class)->getArgument(7));
        self::assertEquals([new Reference('doctrine.dbal.logs_connection')], $container->getDefinition(ReportSchemaListener::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'logs']], $container->getDefinition(ReportSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference(ReportRepository::class), new Reference('clock'), 7], $container->getDefinition(PurgeReportsCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(PurgeReportsCommand::class)->hasTag('console.command'));
        self::assertSame('/csp', $this->load(['reporting' => ['uri' => '/csp']])->getDefinition(ReportEndpoint::class)->getArgument(2));
    }

    public function testTheReportingCollaboratorsTurnedOff(): void
    {
        $container = $this->load(['reporting' => ['rate_limit' => false, 'deduplication' => false, 'client_address' => false]]);
        $arguments = $container->getDefinition(ReportReceiver::class)->getArguments();

        self::assertNull($arguments[4]);
        self::assertNull($arguments[5]);
        self::assertNull($arguments[6]);
        self::assertFalse($container->hasDefinition(RateLimiterThrottle::class));
        self::assertFalse($container->hasDefinition(CacheDeduplicator::class));
        self::assertFalse($container->hasDefinition(TruncatedClientAddress::class));
    }

    public function testReportingTurnedOff(): void
    {
        $container = $this->load(['reporting' => false]);

        self::assertFalse($container->getDefinition(ReportEndpoint::class)->getArgument(1));
        self::assertFalse($container->hasDefinition(ReportReceiver::class));
        self::assertFalse($container->hasDefinition(ReportController::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'an invalid source' => [['policies' => ['default' => ['directives' => ['script-src' => ['self']]]]], 'phil_browser_security.policies: "self" is not a value of "script-src", a keyword is written between single quotes.'];
        yield 'an unknown directive' => [['policies' => ['default' => ['directives' => ['scripts' => ["'self'"]]]]], 'phil_browser_security.policies: "scripts" is not a directive'];
        yield 'a directive that is a number' => [['policies' => ['default' => ['directives' => ['img-src' => 3]]]], 'phil_browser_security.policies.default.directives.img-src: a list of sources, or true.'];
        yield 'an unknown parent' => [['policies' => ['default' => ['extends' => 'base']]], 'phil_browser_security.policies: The policy "default" extends "base", which is not declared.'];
        yield 'an unknown enforced policy' => [['enforce' => 'site'], 'phil_browser_security.enforce: no policy is named "site".'];
        yield 'an enforced policy that is not a name' => [['enforce' => true], 'phil_browser_security.enforce: no policy is named "bool".'];
        yield 'an unknown report-only policy' => [['report_only' => 'strict'], 'phil_browser_security.report_only: no policy is named "strict".'];
        yield 'a report address over http' => [['reporting' => ['uri' => 'http://example.com/r']], 'phil_browser_security.reporting.uri: an https address or a path, without space, quote, comma nor semicolon.'];
        yield 'a report address with a semicolon' => [['reporting' => ['uri' => '/r;script-src *']], 'phil_browser_security.reporting.uri:'];
        yield 'a report address then a new line' => [['reporting' => ['uri' => "/r\n"]], 'phil_browser_security.reporting.uri:'];
        yield 'a report address that is not text' => [['reporting' => ['uri' => 3]], 'phil_browser_security.reporting.uri:'];
        yield 'a group with a quote' => [['reporting' => ['group' => 'a"b']], 'phil_browser_security.reporting.group: letters, digits, "-" and "_" only.'];
        yield 'a group then a new line' => [['reporting' => ['group' => "csp\n"]], 'phil_browser_security.reporting.group:'];
        yield 'a group too long' => [['reporting' => ['group' => str_repeat('a', 65)]], 'phil_browser_security.reporting.group:'];
        yield 'an empty group' => [['reporting' => ['group' => '']], 'phil_browser_security: group must be a string.'];
        yield 'a bad neighbouring header' => [['headers' => ['referrer_policy' => 'never']], 'phil_browser_security.headers: "never" is not a value of Referrer-Policy'];
        yield 'a bad permission' => [['headers' => ['permissions_policy' => ['Camera' => []]]], 'phil_browser_security.headers: "Camera" is not a feature of a permissions policy.'];
        yield 'a header that is not text' => [['headers' => ['cross_origin_opener_policy' => true]], 'phil_browser_security: cross_origin_opener_policy must be a string.'];
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

    public function testTheLongestGroup(): void
    {
        self::assertSame(str_repeat('a', 64), $this->load(['reporting' => ['group' => str_repeat('a', 64)]])->getDefinition(HeaderBuilder::class)->getArgument(3));
    }

    public function testTheOtherListenersOfTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertEquals([new Reference('request_stack')], $container->getDefinition(BrowserSecurityContext::class)->getArguments());
        self::assertTrue($container->getDefinition(BrowserSecurityContext::class)->isPublic());
        self::assertEquals([new Reference(SecurityHeaders::class), new Reference(ReportEndpoint::class), 'csp', ['/_wdt', '/_profiler']], $container->getDefinition(SecurityHeadersListener::class)->getArguments());
        self::assertSame([
            'kernel.event_listener' => [
                ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 31],
                ['event' => 'kernel.controller', 'method' => 'onKernelController'],
                ['event' => 'kernel.response', 'method' => 'onKernelResponse'],
            ],
        ], $container->getDefinition(SecurityHeadersListener::class)->getTags());
        self::assertSame([true, [], 308, 443], $container->getDefinition(HttpsRedirectListener::class)->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 30]]], $container->getDefinition(HttpsRedirectListener::class)->getTags());
        self::assertSame(['%kernel.secret%', []], $container->getDefinition(CookieSigner::class)->getArguments());
        self::assertEquals([new Reference(CookieSigner::class), []], $container->getDefinition(SignedCookiesListener::class)->getArguments());
        self::assertSame([
            'kernel.event_listener' => [
                ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 30],
                ['event' => 'kernel.response', 'method' => 'onKernelResponse', 'priority' => -1024],
            ],
        ], $container->getDefinition(SignedCookiesListener::class)->getTags());
        self::assertEquals([ExternalRedirectAction::Refuse, [], '/', ['/_wdt', '/_profiler'], new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(ExternalRedirectListener::class)->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => 'kernel.response', 'method' => 'onKernelResponse', 'priority' => 16]]], $container->getDefinition(ExternalRedirectListener::class)->getTags());
        self::assertSame([['cookies', 'storage']], $container->getDefinition(ClearSiteDataLogoutListener::class)->getArguments());
        self::assertTrue($container->getDefinition(ClearSiteDataLogoutListener::class)->hasTag('kernel.event_subscriber'));
    }

    public function testADebugKernelIsNotHeldToHttps(): void
    {
        $container = $this->load([], debug: true);

        self::assertFalse($container->getDefinition(HttpsRedirectListener::class)->getArgument(0));
        self::assertArrayNotHasKey('Strict-Transport-Security', $this->headerValues($container));
    }

    public function testHttpsChosenByTheConfiguration(): void
    {
        $on = $this->load(['https' => ['redirect' => ['enabled' => true, 'status' => 301, 'port' => 8443, 'exclude_paths' => ['/health']], 'strict_transport_security' => ['enabled' => true, 'max_age' => 60, 'include_subdomains' => true]]], debug: true);
        $off = $this->load(['https' => ['redirect' => ['enabled' => false], 'strict_transport_security' => ['enabled' => false]]]);

        self::assertSame([true, ['/health'], 301, 8443], $on->getDefinition(HttpsRedirectListener::class)->getArguments());
        self::assertSame('max-age=60; includeSubDomains', $this->headerValues($on)['Strict-Transport-Security']);
        self::assertFalse($off->getDefinition(HttpsRedirectListener::class)->getArgument(0));
        self::assertArrayNotHasKey('Strict-Transport-Security', $this->headerValues($off));
    }

    public function testPreload(): void
    {
        $container = $this->load(['https' => ['strict_transport_security' => ['max_age' => 31_536_000, 'include_subdomains' => true, 'preload' => true]]]);

        self::assertSame('max-age=31536000; includeSubDomains; preload', $this->headerValues($container)['Strict-Transport-Security']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badPreloads(): iterable
    {
        yield 'without subdomains' => [['preload' => true]];
        yield 'too short' => [['preload' => true, 'include_subdomains' => true, 'max_age' => 31_535_999]];
    }

    /**
     * @param array<string, mixed> $transport
     */
    #[DataProvider('badPreloads')]
    public function testPreloadNeedsItsConditions(array $transport): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"preload" needs "include_subdomains" and a "max_age" of one year or more.');

        $this->load(['https' => ['strict_transport_security' => $transport]]);
    }

    public function testEveryHeaderOfTheConfiguration(): void
    {
        $values = $this->headerValues($this->load(['headers' => [
            'cross_origin_opener_policy_report_only' => 'same-origin',
            'cross_origin_embedder_policy_report_only' => 'credentialless',
            'cross_origin_resource_policy' => 'cross-origin',
            'content_type_options' => null,
            'permitted_cross_domain_policies' => 'master-only',
            'origin_agent_cluster' => '?0',
            'frame_options' => ['default' => null],
            'referrer_policy' => null,
            'integrity_policy' => ['enabled' => true, 'blocked_destinations' => ['script', 'style']],
            'integrity_policy_report_only' => true,
        ]]));

        self::assertSame([
            'Cross-Origin-Opener-Policy-Report-Only' => 'same-origin',
            'Cross-Origin-Embedder-Policy-Report-Only' => 'credentialless',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'master-only',
            'Origin-Agent-Cluster' => '?0',
            'Integrity-Policy' => ['script', 'style'],
            'Integrity-Policy-Report-Only' => ['script'],
            'Strict-Transport-Security' => 'max-age=31536000',
        ], $values);
    }

    public function testTheIntegrityPolicyBlocksScriptsByDefault(): void
    {
        $values = $this->headerValues($this->load(['headers' => ['integrity_policy' => true, 'integrity_policy_report_only' => ['enabled' => true, 'blocked_destinations' => ['a' => 'style']]]]));

        self::assertSame(['script'], $values['Integrity-Policy']);
        self::assertSame(['style'], $values['Integrity-Policy-Report-Only']);
    }

    public function testTheFramePaths(): void
    {
        $container = $this->load(['headers' => ['frame_options' => ['paths' => ['/embed' => null, '/admin' => 'DENY', '/my-page' => 'DENY']]]]);

        self::assertSame(['/embed' => null, '/admin' => 'DENY', '/my-page' => 'DENY'], $container->getDefinition(SecurityHeaders::class)->getArgument(1));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badOptions(): iterable
    {
        yield 'a frame path without slash' => [['headers' => ['frame_options' => ['paths' => ['embed' => null]]]], 'phil_browser_security.headers.frame_options.paths: "embed" is a path starting with "/", with a value or null.'];
        yield 'a frame option that is not text' => [['headers' => ['frame_options' => ['paths' => ['/embed' => 5]]]], 'phil_browser_security.headers.frame_options.paths: "/embed" is a path starting with "/", with a value or null.'];
        yield 'a negative age' => [['https' => ['strict_transport_security' => ['max_age' => -1]]], 'Should be greater than or equal to 0'];
        yield 'a fallback ending with a newline' => [['external_redirects' => ['fallback' => "/home\n"]], 'phil_browser_security.external_redirects.fallback: a path of the site'];
        yield 'a frame option of a path' => [['headers' => ['frame_options' => ['paths' => ['/embed' => 'ALLOW']]]], 'phil_browser_security.headers: "ALLOW" is not a value of X-Frame-Options'];
        yield 'a cookie name' => [['signed_cookies' => ['names' => ['a b']]], 'phil_browser_security.signed_cookies.names: "a b" is not a value of signed-cookies.'];
        yield 'a host' => [['external_redirects' => ['allow' => ['https://a']]], 'phil_browser_security.external_redirects.allow:'];
        yield 'a directive at logout' => [['clear_site_data' => ['on_logout' => ['session']]], 'phil_browser_security.clear_site_data.on_logout:'];
        yield 'a fallback elsewhere' => [['external_redirects' => ['fallback' => 'https://a.example.com/']], 'phil_browser_security.external_redirects.fallback: a path of the site'];
        yield 'a fallback of two slashes' => [['external_redirects' => ['fallback' => '//a.example.com/']], 'phil_browser_security.external_redirects.fallback: a path of the site'];
        yield 'a fallback with a backslash' => [['external_redirects' => ['fallback' => '/\\a.example.com/']], 'phil_browser_security.external_redirects.fallback: a path of the site'];
        yield 'a fallback with a space' => [['external_redirects' => ['fallback' => '/a b']], 'phil_browser_security.external_redirects.fallback: a path of the site'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badOptions')]
    public function testABadOptionFailsTheBuild(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testTheOptionsOfTheCookiesAndTheRedirects(): void
    {
        $container = $this->load([
            'signed_cookies' => ['names' => ['theme'], 'secret' => 's3', 'previous_secrets' => ['s2']],
            'external_redirects' => ['action' => 'fallback', 'fallback' => '/home?x=1', 'allow' => ['A.example.com']],
        ]);

        self::assertSame(['s3', ['s2']], $container->getDefinition(CookieSigner::class)->getArguments());
        self::assertSame(['theme'], $container->getDefinition(SignedCookiesListener::class)->getArgument(1));
        self::assertSame(ExternalRedirectAction::Fallback, $container->getDefinition(ExternalRedirectListener::class)->getArgument(0));
        self::assertSame(['a.example.com'], $container->getDefinition(ExternalRedirectListener::class)->getArgument(1));
        self::assertSame('/home?x=1', $container->getDefinition(ExternalRedirectListener::class)->getArgument(2));
    }

    public function testWhatCanBeTurnedOff(): void
    {
        $container = $this->load(['external_redirects' => false, 'clear_site_data' => ['on_logout' => []], 'reporting' => false]);

        self::assertFalse($container->hasDefinition(ExternalRedirectListener::class));
        self::assertFalse($container->hasDefinition(ClearSiteDataLogoutListener::class));
        self::assertNull($container->getDefinition(SecurityHeaders::class)->getArgument(2));
    }

    public function testASingleServerIsNotHeldToASharedPool(): void
    {
        $container = $this->load(['reporting' => ['rate_limit' => ['single_server' => true]]]);

        self::assertFalse($container->hasParameter(RateLimitGuard::STORES_PARAMETER));
    }

    /**
     * @return array<string, mixed>
     */
    private function headerValues(ContainerBuilder $container): array
    {
        /** @var array<string, mixed> */
        return $container->getDefinition(SecurityHeaders::class)->getArgument(0);
    }

    /**
     * @param array<string, mixed>        $config
     * @param array<string, class-string> $bundles
     */
    private function load(array $config, array $bundles = [], bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $container->setParameter('kernel.debug', $debug);

        $extension = new PhilBrowserSecurityBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}
