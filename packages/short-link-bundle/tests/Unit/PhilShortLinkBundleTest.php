<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit;

use Phil\QrCode\ErrorCorrection;
use Phil\RateLimit\RateLimitGuard;
use Phil\RedirectsBundle\Application\LocationBuilder;
use Phil\ShortLinkBundle\Application\Code\CodeGenerator;
use Phil\ShortLinkBundle\Application\Code\DefaultBlocklist;
use Phil\ShortLinkBundle\Application\Code\SqidCodec;
use Phil\ShortLinkBundle\Application\Contract\VisitorClassifier;
use Phil\ShortLinkBundle\Application\Contract\VisitRecorder;
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
use Phil\ShortLinkBundle\PhilShortLinkBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

#[CoversClass(PhilShortLinkBundle::class)]
final class PhilShortLinkBundleTest extends TestCase
{
    private const array DOMAINS = ['domains' => [['host' => 'go.example.com']]];

    public function testTheBundleCanBeDisabled(): void
    {
        $container = $this->load([['enabled' => false]]);

        self::assertFalse($container->getParameter('phil_short_link.enabled'));
        self::assertFalse($container->hasDefinition(ShortLinks::class));
    }

    public function testTheAlias(): void
    {
        self::assertSame('phil_short_link', $this->extension()->getAlias());
    }

    public function testADomainIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_short_link.domains names at least one domain that serves the short links.');

        $this->load([[]]);
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([self::DOMAINS]);

        self::assertTrue($container->getParameter('phil_short_link.enabled'));
        self::assertSame([SqidCodec::DEFAULT_ALPHABET, 6, DefaultBlocklist::WORDS], $container->getDefinition(SqidCodec::class)->getArguments());
        self::assertSame(2 ** 40 - 1, $container->getDefinition(CodeGenerator::class)->getArgument(1));
        self::assertSame([[], false], $container->getDefinition('phil_short_link.target_policy')->getArguments());

        $links = $container->getDefinition(ShortLinks::class)->getArguments();
        self::assertSame([false, true, false, null], [$links[6], $links[7], $links[8], $links[9]]);
        self::assertEquals(new Reference(RateLimiterCreationThrottle::class), $links[10]);
        self::assertTrue($container->getDefinition(ShortLinks::class)->isPublic());
        self::assertSame(['id' => 'phil_short_link_creation', 'policy' => 'sliding_window', 'limit' => 30, 'interval' => '1 hour'], $container->getDefinition('phil_short_link.creation_limiter')->getArgument(0));
        self::assertSame(['phil_short_link.rate_limit.cache_pool' => ['store' => 'cache.app', 'switch' => 'enabled']], RateLimitGuard::watched($container));

        self::assertNull($container->getDefinition(ShortLinkResolver::class)->getArgument(5));
        self::assertSame([ErrorCorrection::Medium, 4, '#000000', '#ffffff', 256, 8], \array_slice($container->getDefinition(ShortLinkQrCodes::class)->getArguments(), 1));

        $listener = $container->getDefinition(ShortLinkListener::class);
        self::assertSame([
            ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 40],
            ['event' => 'kernel.terminate', 'method' => 'onKernelTerminate'],
        ], $listener->getTag('kernel.event_listener'));
        self::assertSame([['method' => 'reset']], $listener->getTag('kernel.reset'));
        self::assertEquals(new Reference(VisitorClassifier::class), $listener->getArgument(3));
        self::assertSame(KnownVisitorClassifier::class, (string) $container->getAlias(VisitorClassifier::class));
        self::assertSame([[], []], $container->getDefinition(KnownVisitorClassifier::class)->getArguments());

        self::assertSame(['ROLE_ADMIN', PhilShortLinkBundle::DEFAULT_LAYOUT], \array_slice($container->getDefinition(ShortLinkAdminController::class)->getArguments(), 10, 2));
        self::assertSame([[]], $container->getDefinition(ShortLinkType::class)->getTag('form.type'));
        self::assertSame([true, 'UTC'], \array_slice($container->getDefinition(ShortLinkType::class)->getArguments(), 1));
        self::assertFalse($container->hasDefinition(AnalyticsVisitRecorder::class));
        self::assertFalse($container->hasDefinition(SafeHttpTargetCheck::class));
        self::assertEquals(new Reference('doctrine.orm.entity_manager'), $container->getDefinition(DoctrineShortLinkRepository::class)->getArgument(0));
    }

    public function testTheDomainsAreDefinitions(): void
    {
        $container = $this->load([['domains' => [['host' => 'go.example.com'], ['host' => 'lien.example.fr', 'locale' => 'fr', 'scheme' => 'http']], 'allow_localhost' => true]]);
        $arguments = $container->getDefinition(ShortLinkDomains::class)->getArguments();

        self::assertIsArray($arguments[0]);
        self::assertSame([['go.example.com', null, 'https'], ['lien.example.fr', 'fr', 'http']], array_map(static fn (mixed $domain): array => $domain instanceof Definition ? $domain->getArguments() : [], $arguments[0]));
        self::assertTrue($arguments[1]);
    }

    public function testEveryOptionReachesItsService(): void
    {
        $container = $this->load([[
            ...self::DOMAINS,
            'home_url' => 'https://example.com/accueil',
            'entity_manager' => 'doctrine.orm.links_entity_manager',
            'code' => ['alphabet' => 'abcdef', 'min_length' => 3, 'max_number' => 2_000_000, 'blocklist' => ['bad'], 'default_blocklist' => false, 'custom' => false],
            'targets' => ['allowed_hosts' => ['example.com'], 'any_host' => true, 'check' => true],
            'rate_limit' => ['limit' => 5, 'interval' => '1 day', 'cache_pool' => 'cache.shared'],
            'qr_code' => ['level' => 'H', 'quiet_zone' => 2, 'dark' => '#111111', 'light' => '#eeeeee', 'size' => 512, 'scale' => 4],
            'admin' => ['role' => 'ROLE_EDITOR', 'layout' => 'admin.html.twig', 'timezone' => 'America/Toronto'],
            'analytics' => ['site' => 'main', 'event' => 'go'],
            'visitors' => ['link_previews' => ['ChatApp'], 'robots' => ['Harvester', 'Indexer']],
        ]]);

        self::assertSame([['ChatApp'], ['Harvester', 'Indexer']], $container->getDefinition(KnownVisitorClassifier::class)->getArguments());

        self::assertSame(['abcdef', 3, ['bad']], $container->getDefinition(SqidCodec::class)->getArguments());
        self::assertSame(2_000_000, $container->getDefinition(CodeGenerator::class)->getArgument(1));
        self::assertSame([['example.com'], false], $container->getDefinition('phil_short_link.target_policy')->getArguments());
        $links = $container->getDefinition(ShortLinks::class)->getArguments();
        self::assertSame([true, false], [$links[6], $links[7]]);
        self::assertEquals(new Reference(SafeHttpTargetCheck::class), $links[9]);
        self::assertSame([['method' => 'reset']], $container->getDefinition('phil_short_link.safe_http_client')->getTag('kernel.reset'));
        self::assertSame('https://example.com/accueil', $container->getDefinition(ShortLinkResolver::class)->getArgument(5));
        self::assertEquals(new Reference('doctrine.orm.links_entity_manager'), $container->getDefinition(DoctrineShortLinkRepository::class)->getArgument(0));
        self::assertSame(['id' => 'phil_short_link_creation', 'policy' => 'sliding_window', 'limit' => 5, 'interval' => '1 day'], $container->getDefinition('phil_short_link.creation_limiter')->getArgument(0));
        self::assertEquals(new Reference('cache.shared'), $container->getDefinition('phil_short_link.creation_limiter.storage')->getArgument(0));
        self::assertSame([ErrorCorrection::High, 2, '#111111', '#eeeeee', 512, 4], \array_slice($container->getDefinition(ShortLinkQrCodes::class)->getArguments(), 1));
        self::assertSame(['ROLE_EDITOR', 'admin.html.twig'], \array_slice($container->getDefinition(ShortLinkAdminController::class)->getArguments(), 10, 2));
        self::assertSame([false, 'America/Toronto'], \array_slice($container->getDefinition(ShortLinkType::class)->getArguments(), 1));
        self::assertSame(['main', 'go'], \array_slice($container->getDefinition(AnalyticsVisitRecorder::class)->getArguments(), 1));
        self::assertTrue($container->getDefinition(AnalyticsVisitRecorder::class)->hasTag(PhilShortLinkBundle::VISIT_RECORDER_TAG));
    }

    public function testTheApplicationsLimiterAndAnApplicationOnOneServer(): void
    {
        $container = $this->load([[...self::DOMAINS, 'rate_limit' => ['limiter' => 'app.limiter']]]);

        self::assertFalse($container->hasDefinition('phil_short_link.creation_limiter'));
        self::assertEquals(new Reference('app.limiter'), $container->getDefinition(RateLimiterCreationThrottle::class)->getArgument(0));
        self::assertSame([], RateLimitGuard::watched($container));
        self::assertSame([], RateLimitGuard::watched($this->load([[...self::DOMAINS, 'rate_limit' => ['single_server' => true]]])));
    }

    public function testTheRateLimitAndTheAdministrationCanBeTurnedOff(): void
    {
        $container = $this->load([[...self::DOMAINS, 'rate_limit' => false, 'admin' => false]]);

        self::assertNull($container->getDefinition(ShortLinks::class)->getArgument(10));
        self::assertFalse($container->hasDefinition(RateLimiterCreationThrottle::class));
        self::assertFalse($container->hasDefinition(ShortLinkAdminController::class));
        self::assertFalse($container->hasDefinition(ShortLinkType::class));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function homeUrls(): iterable
    {
        yield 'a path' => ['/home'];
        yield 'another scheme' => ['ftp://example.com/'];
        yield 'a space' => ['https://exa mple.com/'];
        yield 'no host' => ['https:///x'];
        yield 'a line break at the end' => ["https://example.com/\n"];
    }

    public function testTheSchemeOfTheHomeIsNotCaseSensitive(): void
    {
        self::assertSame('HTTPS://Example.com/', $this->load([[...self::DOMAINS, 'home_url' => 'HTTPS://Example.com/']])->getDefinition(ShortLinkResolver::class)->getArgument(5));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function bounds(): iterable
    {
        yield 'the shortest code of none' => [['code' => ['min_length' => 0]], true];
        yield 'a negative shortest code' => [['code' => ['min_length' => -1]], false];
        yield 'the longest shortest code' => [['code' => ['min_length' => SqidCodec::MAX_LENGTH]], true];
        yield 'a shortest code too long' => [['code' => ['min_length' => SqidCodec::MAX_LENGTH + 1]], false];
        yield 'a million numbers' => [['code' => ['max_number' => 1_000_000]], true];
        yield 'under a million numbers' => [['code' => ['max_number' => 999_999]], false];
        yield 'one creation an hour' => [['rate_limit' => ['limit' => 1]], true];
        yield 'no creation' => [['rate_limit' => ['limit' => 0]], false];
        yield 'a quiet zone of none' => [['qr_code' => ['quiet_zone' => 0]], true];
        yield 'a negative quiet zone' => [['qr_code' => ['quiet_zone' => -1]], false];
        yield 'the widest quiet zone' => [['qr_code' => ['quiet_zone' => 16]], true];
        yield 'a quiet zone too wide' => [['qr_code' => ['quiet_zone' => 17]], false];
        yield 'a size of none' => [['qr_code' => ['size' => 0]], true];
        yield 'a negative size' => [['qr_code' => ['size' => -1]], false];
        yield 'the largest size' => [['qr_code' => ['size' => 4096]], true];
        yield 'a size too large' => [['qr_code' => ['size' => 4097]], false];
        yield 'a module of one pixel' => [['qr_code' => ['scale' => 1]], true];
        yield 'a module of none' => [['qr_code' => ['scale' => 0]], false];
        yield 'the largest module' => [['qr_code' => ['scale' => 32]], true];
        yield 'a module too large' => [['qr_code' => ['scale' => 33]], false];
        yield 'the lowest level of correction' => [['qr_code' => ['level' => 'L']], true];
        yield 'a level of correction that is none' => [['qr_code' => ['level' => 'X']], false];
        yield 'a secure domain' => [['domains' => [['host' => 'go.example.com', 'scheme' => 'https']]], true];
        yield 'a domain without security' => [['domains' => [['host' => 'go.example.com', 'scheme' => 'http']]], true];
        yield 'a scheme that is none' => [['domains' => [['host' => 'go.example.com', 'scheme' => 'ftp']]], false];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('bounds')]
    public function testTheBoundsOfTheOptions(array $options, bool $accepted): void
    {
        if (!$accepted) {
            $this->expectException(InvalidConfigurationException::class);
        }

        $container = $this->load([[...self::DOMAINS, ...$options]]);

        self::assertTrue($container->hasDefinition(ShortLinks::class));
    }

    public function testTheWiringOfTheServicesThatTheRequestNeeds(): void
    {
        $container = $this->load([[...self::DOMAINS, 'allow_localhost' => true, 'targets' => ['check' => true]]]);

        self::assertArrayHasKey(VisitRecorder::class, $container->getAutoconfiguredInstanceof());
        self::assertTrue($container->getAutoconfiguredInstanceof()[VisitRecorder::class]->hasTag(PhilShortLinkBundle::VISIT_RECORDER_TAG));
        self::assertTrue($container->getAlias(ShortLinkRepository::class)->isPublic());

        $builder = $container->getDefinition('phil_short_link.location_builder');
        self::assertSame(LocationBuilder::class, $builder->getClass());
        self::assertEquals([new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), true], $builder->getArguments());

        self::assertEquals([new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition('phil_short_link.safe_http_client')->getArguments());
        self::assertEquals([new Reference('phil_short_link.safe_http_client')], $container->getDefinition(SafeHttpTargetCheck::class)->getArguments());
        self::assertEquals(new Reference('phil_short_link.creation_limiter.storage'), $container->getDefinition('phil_short_link.creation_limiter')->getArgument(1));
        self::assertSame(CacheStorage::class, $container->getDefinition('phil_short_link.creation_limiter.storage')->getClass());
    }

    #[DataProvider('homeUrls')]
    public function testTheHomeIsAnAbsoluteUrl(string $url): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_short_link.home_url is an absolute http or https URL.');

        $this->load([[...self::DOMAINS, 'home_url' => $url]]);
    }

    public function testAnEmptyHomeIsNone(): void
    {
        self::assertNull($this->load([[...self::DOMAINS, 'home_url' => '']])->getDefinition(ShortLinkResolver::class)->getArgument(5));
        self::assertSame('http://example.com', $this->load([[...self::DOMAINS, 'home_url' => 'http://example.com']])->getDefinition(ShortLinkResolver::class)->getArgument(5));
    }

    public function testTheMappingIsPrependedForDoctrine(): void
    {
        $container = new ContainerBuilder();
        $doctrine = new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        };
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);
        self::assertSame([], $container->getExtensionConfig('doctrine'), 'nothing without Doctrine');

        $container->registerExtension($doctrine);
        $extension->prepend($container);
        $doctrineConfig = $container->getExtensionConfig('doctrine')[0] ?? null;
        self::assertIsArray($doctrineConfig);
        $orm = $doctrineConfig['orm'] ?? null;
        self::assertIsArray($orm);
        self::assertIsArray($orm['mappings']);
        $mapping = $orm['mappings']['PhilShortLinkBundle'];
        self::assertIsArray($mapping);
        self::assertSame(['attribute', false, 'Phil\ShortLinkBundle\Domain\Model'], [$mapping['type'], $mapping['is_bundle'], $mapping['prefix']]);
        self::assertIsString($mapping['dir']);
        self::assertDirectoryExists($mapping['dir']);
        self::assertSame(realpath(\dirname(__DIR__, 2).'/src/Domain/Model'), realpath($mapping['dir']));
    }

    public function testTheGuardOfTheRateLimitIsAdded(): void
    {
        $container = new ContainerBuilder();
        new PhilShortLinkBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof RateLimitGuard));
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
        $extension = new PhilShortLinkBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
