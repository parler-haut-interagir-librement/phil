<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use Doctrine\DBAL\DriverManager;
use LogicException;
use Phil\PrivacyBundle\Application\Contract\ExportReadme;
use Phil\PrivacyBundle\Application\Contract\GuestLinkSender;
use Phil\PrivacyBundle\Application\Contract\GuestRequestStore;
use Phil\PrivacyBundle\Application\Contract\GuestSubjects;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Export\ExportPackage;
use Phil\PrivacyBundle\Application\Export\PlainExportReadme;
use Phil\PrivacyBundle\Application\GuestRequest\EmailGuestSubjects;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Domain\Cookie\DeclaredCookie;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentsCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalGuestRequests;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\CookieServices;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\GuestRequestServices;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsConfiguration;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsServices;
use Phil\PrivacyBundle\Infrastructure\Http\GuestRequestController;
use Phil\PrivacyBundle\Infrastructure\Http\UndeclaredCookieListener;
use Phil\PrivacyBundle\Infrastructure\Mailer\MailerGuestLinkSender;
use Phil\PrivacyBundle\Infrastructure\Routing\GuestLinkOrigin;
use Phil\PrivacyBundle\Infrastructure\Twig\EmbedExtension;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigExportReadme;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(CookieServices::class)]
#[CoversClass(PhilPrivacyBundle::class)]
#[CoversClass(GuestRequestServices::class)]
#[CoversClass(RightsConfiguration::class)]
#[CoversClass(RightsServices::class)]
#[CoversClass(PrivacyServicesPass::class)]
final class AdditionsWiringTest extends TestCase
{
    private const array GUESTS = ['guest_requests' => ['enabled' => true, 'from' => 'privacy@example.org']];

    public function testTheInventoryIsReadFromItsFileWhichIsAResourceOfTheContainer(): void
    {
        $file = __DIR__.'/Fixture/cookies.yaml';
        $container = $this->load(['consent' => ['categories' => ['analytics' => []]], 'cookies' => ['inventory' => $file]]);

        $inventory = $container->getDefinition(CookieInventory::class);
        self::assertTrue($inventory->isPublic());
        self::assertSame(['phil_privacy_consent', 'P6M'], \array_slice($inventory->getArguments(), 1));
        self::assertIsArray($inventory->getArgument(0));
        self::assertArrayHasKey('analytics', $inventory->getArgument(0));
        $resources = array_map(static fn (object $resource): string => (string) $resource, $container->getResources());
        self::assertContains((string) new FileExistenceResource($file), $resources);
        self::assertContains((string) new FileResource($file), $resources);
    }

    public function testWithoutAFileTheInventoryHoldsOnlyTheConsentCookie(): void
    {
        $container = $this->load(['cookies' => ['inventory' => __DIR__.'/Fixture/none.yaml']]);
        self::assertSame([], $container->getDefinition(CookieInventory::class)->getArgument(0));

        $empty = $this->load(['cookies' => ['inventory' => __DIR__.'/Fixture/empty-cookies.yaml']]);
        self::assertSame([], $empty->getDefinition(CookieInventory::class)->getArgument(0));
    }

    public function testTheDefaultFileIsTheProjectsWhenThereIsAProject(): void
    {
        $container = $this->load([], ['kernel.project_dir' => '/nowhere']);
        $resources = array_map(static fn (object $resource): string => (string) $resource, $container->getResources());

        self::assertContains((string) new FileExistenceResource('/nowhere/config/privacy/cookies.yaml'), $resources);
        self::assertSame([], $this->load([])->getResources(), 'Without a project, no file is looked for.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badInventories(): iterable
    {
        yield 'unknown category' => ['bad-cookies.yaml', 'phil_privacy.cookies.inventory: The cookie "_m" is declared under "marketing", which is not a consent category.'];
        yield 'broken YAML' => ['broken-cookies.yaml', 'phil_privacy.cookies.inventory: '];
        yield 'a scalar' => ['scalar-cookies.yaml', 'phil_privacy.cookies.inventory: the file maps each consent category to its cookies.'];
    }

    #[DataProvider('badInventories')]
    public function testABadInventoryStopsTheBuild(string $file, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load(['consent' => ['categories' => ['analytics' => []]], 'cookies' => ['inventory' => __DIR__.'/Fixture/'.$file]]);
    }

    public function testTheWatchIsOnInDebugOrWhenAsked(): void
    {
        self::assertFalse($this->load([])->hasDefinition(UndeclaredCookieListener::class));
        self::assertFalse($this->load([], ['kernel.debug' => false])->hasDefinition(UndeclaredCookieListener::class));
        self::assertFalse($this->load(['cookies' => ['watch' => false]], ['kernel.debug' => true])->hasDefinition(UndeclaredCookieListener::class));

        $listener = $this->load([], ['kernel.debug' => true])->getDefinition(UndeclaredCookieListener::class);
        self::assertSame([['event' => 'kernel.response', 'method' => 'onKernelResponse']], $listener->getTag('kernel.event_listener'));
        self::assertEquals([new Reference(CookieInventory::class), new Reference('logger')], $listener->getArguments());
        self::assertTrue($this->load(['cookies' => ['watch' => true]])->hasDefinition(UndeclaredCookieListener::class));
    }

    public function testTheGuestRequestsAreOffByDefaultAndTheirPagesAnswerNotFound(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(GuestRequests::class));
        self::assertFalse($container->hasDefinition(GuestRequestServices::PER_CLIENT));
        self::assertEquals(new Reference(GuestRequests::class, 2), $container->getDefinition(GuestRequestController::class)->getArgument(1));
        self::assertTrue($container->getDefinition(GuestRequestController::class)->hasTag('controller.service_arguments'));
    }

    public function testTurnedOnTheGuestRequestsAreWiredWithTheirLimits(): void
    {
        $container = $this->load(['guest_requests' => ['enabled' => true, 'from' => 'privacy@example.org', 'link_lifetime' => 'PT30M', 'per_client' => ['limit' => 9]]]);

        $guests = $container->getDefinition(GuestRequests::class);
        self::assertTrue($guests->isPublic());
        self::assertSame(['%kernel.secret%', 'PT30M'], \array_slice($guests->getArguments(), 10, 2));
        self::assertSame(MailerGuestLinkSender::class, (string) $container->getAlias(GuestLinkSender::class));
        self::assertSame(EmailGuestSubjects::class, (string) $container->getAlias(GuestSubjects::class));
        self::assertSame(DbalGuestRequests::class, (string) $container->getAlias(GuestRequestStore::class));
        self::assertEquals([new Reference('mailer.transports'), new Reference('twig'), new Reference('router'), 'privacy@example.org', new Reference(GuestLinkOrigin::class)], $container->getDefinition(MailerGuestLinkSender::class)->getArguments());
        self::assertEquals([new Reference('router'), '%phil_privacy.public_origin%', '%kernel.debug%'], $container->getDefinition(GuestLinkOrigin::class)->getArguments());
        self::assertTrue($container->getParameter(PhilPrivacyBundle::MAILED_LINKS));
        self::assertNull($container->getParameter(GuestLinkOrigin::OPTION));
        self::assertSame(['id' => GuestRequestServices::PER_CLIENT, 'policy' => 'sliding_window', 'limit' => 9, 'interval' => '1 hour'], $container->getDefinition(GuestRequestServices::PER_CLIENT)->getArgument(0));
        self::assertSame(['id' => GuestRequestServices::PER_ADDRESS, 'policy' => 'sliding_window', 'limit' => 3, 'interval' => '1 day'], $container->getDefinition(GuestRequestServices::PER_ADDRESS)->getArgument(0));
        self::assertSame(RateLimiterFactory::class, $container->getDefinition(GuestRequestServices::PER_CLIENT)->getClass());
    }

    public function testTheMailedLinksAndTheOriginAreParametersOfTheGuestConfiguration(): void
    {
        $origin = $this->load(['public_origin' => 'https://example.org', ...self::GUESTS]);
        self::assertSame('https://example.org', $origin->getParameter(GuestLinkOrigin::OPTION));
        self::assertTrue($origin->getParameter(PhilPrivacyBundle::MAILED_LINKS));

        $off = $this->load([]);
        self::assertNull($off->getParameter(GuestLinkOrigin::OPTION));
        self::assertFalse($off->getParameter(PhilPrivacyBundle::MAILED_LINKS));

        $own = $this->load(['guest_requests' => ['enabled' => true, 'sender' => 'App\\Mailer']]);
        self::assertFalse($own->getParameter(PhilPrivacyBundle::MAILED_LINKS));

        $disabledWithoutSender = $this->load(['guest_requests' => ['enabled' => false, 'from' => 'privacy@example.org']]);
        self::assertFalse($disabledWithoutSender->getParameter(PhilPrivacyBundle::MAILED_LINKS));
    }

    public function testNamedPartsReplaceTheBundles(): void
    {
        $container = $this->load(['guest_requests' => ['enabled' => true, 'sender' => 'app.sender', 'subjects' => 'app.subjects', 'store' => 'app.store', 'cache' => 'cache.limits']]);

        self::assertSame('app.sender', (string) $container->getAlias(GuestLinkSender::class));
        self::assertSame('app.subjects', (string) $container->getAlias(GuestSubjects::class));
        self::assertSame('app.store', (string) $container->getAlias(GuestRequestStore::class));
        self::assertFalse($container->hasDefinition(MailerGuestLinkSender::class));
    }

    public function testTurnedOnWithoutASenderOrWithABadLifetimeStopsTheBuild(): void
    {
        foreach ([
            'phil_privacy.guest_requests.from is the sender of the links' => ['guest_requests' => ['enabled' => true]],
            'phil_privacy.guest_requests.link_lifetime: ' => ['guest_requests' => ['enabled' => true, 'from' => 'a@b.c', 'link_lifetime' => 'an hour']],
        ] as $message => $config) {
            try {
                $this->load($config);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringStartsWith($message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheIncidentsAndTheExportAreWired(): void
    {
        $container = $this->load([]);

        self::assertSame(DbalIncidentRegister::class, (string) $container->getAlias(IncidentRegister::class));
        self::assertTrue($container->getAlias(IncidentRegister::class)->isPublic());
        self::assertTrue($container->getDefinition(DbalIncidentRegister::class)->hasTag(RightsServices::PURGER_TAG));
        self::assertTrue($container->getDefinition(IncidentCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(IncidentsCommand::class)->hasTag('console.command'));
        self::assertSame(TwigExportReadme::class, (string) $container->getAlias(ExportReadme::class));
        self::assertEquals([new Reference(ExportReadme::class), new Reference('clock')], $container->getDefinition(ExportPackage::class)->getArguments());
        self::assertSame('app.incidents', (string) $this->load(['incidents' => ['store' => 'app.incidents']])->getAlias(IncidentRegister::class));
        self::assertTrue($container->getDefinition(EmbedExtension::class)->hasTag('twig.attribute_extension'));
    }

    public function testWithoutADatabaseTheIncidentsAndTheGuestTableGo(): void
    {
        $container = $this->load([]);
        new PrivacyServicesPass()->process($container);

        foreach ([DbalIncidentRegister::class, DbalGuestRequests::class, IncidentCommand::class, IncidentsCommand::class, ExportPackage::class, TwigExportReadme::class, EmbedExtension::class, GuestRequestController::class] as $id) {
            self::assertFalse($container->hasDefinition($id), $id);
        }

        self::assertFalse($container->hasAlias(IncidentRegister::class));
        self::assertSame(PlainExportReadme::class, (string) $container->getAlias(ExportReadme::class));

        $named = $this->load(['incidents' => ['store' => 'app.incidents'], 'guest_requests' => ['enabled' => true, 'store' => 'app.store', 'from' => 'a@b.c']]);
        $this->services($named, ['clock']);
        try {
            new PrivacyServicesPass()->process($named);
        } catch (LogicException) {
        }

        self::assertSame('app.incidents', (string) $named->getAlias(IncidentRegister::class));
        self::assertTrue($named->hasDefinition(IncidentCommand::class));
        self::assertSame('app.store', (string) $named->getAlias(GuestRequestStore::class));
    }

    public function testWithoutALoggerTheWatchGoes(): void
    {
        $container = $this->load(['cookies' => ['watch' => true]]);
        new PrivacyServicesPass()->process($container);
        self::assertFalse($container->hasDefinition(UndeclaredCookieListener::class));

        $logged = $this->load(['cookies' => ['watch' => true]]);
        $this->services($logged, ['logger']);
        new PrivacyServicesPass()->process($logged);
        self::assertTrue($logged->hasDefinition(UndeclaredCookieListener::class));
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function missingParts(): iterable
    {
        $all = ['database_connection', 'clock', 'lock.factory', 'mailer.transports', 'twig', 'router', 'cache.app'];

        yield 'database' => [array_values(array_diff($all, ['database_connection'])), 'needs a GuestRequestStore'];
        yield 'lock' => [array_values(array_diff($all, ['lock.factory'])), 'needs the lock component'];
        yield 'clock' => [array_values(array_diff($all, ['clock'])), 'needs the clock component'];
        yield 'mailer' => [array_values(array_diff($all, ['mailer.transports'])), 'needs symfony/mailer, for the mailer sender'];
        yield 'named sender' => [array_values(array_diff($all, ['mailer.transports'])), 'needs a GuestLinkSender'];
        yield 'twig' => [array_values(array_diff($all, ['twig'])), 'needs Twig'];
        yield 'router' => [array_values(array_diff($all, ['router'])), 'needs the router'];
        yield 'cache' => [array_values(array_diff($all, ['cache.app'])), 'needs the cache pool "cache.app"'];
    }

    /**
     * @param list<string> $present
     */
    #[DataProvider('missingParts')]
    public function testTurnedOnTheGuestRequestsNeedEachOfTheirParts(array $present, string $message): void
    {
        $container = $this->load('needs a GuestLinkSender' === $message ? ['guest_requests' => ['enabled' => true, 'sender' => 'app.sender']] : self::GUESTS);
        $this->services($container, $present);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.guest_requests is enabled and '.$message);

        new PrivacyServicesPass()->process($container);
    }

    public function testWithEveryPartTheGuestRequestsStay(): void
    {
        $container = $this->load(self::GUESTS);
        $this->services($container, ['database_connection', 'clock', 'lock.factory', 'mailer.transports', 'twig', 'router', 'cache.app', 'security.csrf.token_manager']);
        new PrivacyServicesPass()->process($container);

        self::assertTrue($container->hasDefinition(GuestRequests::class));
        self::assertTrue($container->hasDefinition(GuestRequestController::class));
    }

    public function testEveryNewServiceIsBuiltWithTheArgumentsItNeeds(): void
    {
        $container = $this->load([...self::GUESTS, 'consent' => ['categories' => ['analytics' => []]], 'cookies' => ['inventory' => __DIR__.'/Fixture/cookies.yaml', 'watch' => true]], ['kernel.secret' => 'secret', 'kernel.project_dir' => sys_get_temp_dir(), 'kernel.environment' => 'test', 'kernel.debug' => false]);
        $externals = [
            'database_connection' => DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            'lock.factory' => new LockFactory(new InMemoryStore()),
            'clock' => new MockClock(),
            'logger' => new NullLogger(),
            'event_dispatcher' => new EventDispatcher(),
            'messenger.default_bus' => new MessageBus(),
            'request_stack' => new RequestStack(),
            'twig' => new Environment(new ArrayLoader()),
            'security.csrf.token_manager' => self::createStub(CsrfTokenManagerInterface::class),
            'router' => self::createStub(UrlGeneratorInterface::class),
            'security.token_storage' => new TokenStorage(),
            'mailer.transports' => new NullTransport(),
            'cache.app' => new ArrayAdapter(),
        ];

        foreach (array_keys($externals) as $id) {
            $container->register($id)->setSynthetic(true)->setPublic(true);
        }

        $container->addCompilerPass(new PrivacyServicesPass());

        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }

        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }

        $container->compile(true);

        foreach ($externals as $id => $service) {
            $container->set($id, $service);
        }

        foreach ([
            CookieInventory::class => CookieInventory::class,
            ConsentPolicy::class => ConsentPolicy::class,
            UndeclaredCookieListener::class => UndeclaredCookieListener::class,
            EmbedExtension::class => EmbedExtension::class,
            ExportPackage::class => ExportPackage::class,
            ExportReadme::class => TwigExportReadme::class,
            IncidentRegister::class => DbalIncidentRegister::class,
            IncidentCommand::class => IncidentCommand::class,
            IncidentsCommand::class => IncidentsCommand::class,
            GuestRequests::class => GuestRequests::class,
            GuestRequestController::class => GuestRequestController::class,
            GuestLinkSender::class => MailerGuestLinkSender::class,
            GuestRequestStore::class => DbalGuestRequests::class,
            GuestRequestServices::PER_CLIENT => RateLimiterFactory::class,
        ] as $id => $class) {
            self::assertInstanceOf($class, $container->get($id), $id);
        }

        $inventory = $container->get(CookieInventory::class);
        self::assertInstanceOf(CookieInventory::class, $inventory);
        self::assertSame(['phil_privacy_consent', '_a', '_a_ses.*'], array_map(static fn (DeclaredCookie $cookie): string => $cookie->name, $inventory->cookies));
        $policy = $container->get(ConsentPolicy::class);
        self::assertInstanceOf(ConsentPolicy::class, $policy);
        self::assertSame(['_a', '_a_ses.*'], $policy->categories['analytics']->cookies);
    }

    /**
     * @param list<string> $ids
     */
    private function services(ContainerBuilder $container, array $ids): void
    {
        foreach ($ids as $id) {
            $container->register($id, stdClass::class);
        }
    }

    /**
     * @param array<string, mixed>       $config
     * @param array<string, bool|string> $parameters
     */
    private function load(array $config, array $parameters = []): ContainerBuilder
    {
        $bundle = new PhilPrivacyBundle('php://memory');
        $container = new ContainerBuilder();

        foreach ($parameters as $name => $value) {
            $container->setParameter($name, $value);
        }

        $instanceof = [];
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $bundle->loadExtension(new Processor()->processConfiguration($configuration, [$config]), new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__), $container);

        return $container;
    }
}
