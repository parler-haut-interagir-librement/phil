<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use Doctrine\DBAL\DriverManager;
use Phil\LegalNotices\SiteIdentity;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Contract\NoticeOrganisationSource;
use Phil\PrivacyBundle\Application\Incident\IncidentNotices;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentNoticeCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsServices;
use Phil\PrivacyBundle\Infrastructure\LegalNotices\SiteIdentityOrganisation;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigIncidentNotice;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Twig\NoticeFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\Translator;

#[CoversClass(RightsServices::class)]
#[CoversClass(PrivacyServicesPass::class)]
final class IncidentNoticeWiringTest extends TestCase
{
    public function testTheDraftsAreWiredWithTheIdentityOfTheSite(): void
    {
        $container = $this->load();

        self::assertEquals([new Reference(NoticeOrganisationSource::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(IncidentNotices::class)->getArguments());
        self::assertEquals([new Reference('twig')], $container->getDefinition(TwigIncidentNotice::class)->getArguments());
        self::assertEquals([new Reference(IncidentRegister::class), new Reference(IncidentNotices::class), new Reference(TwigIncidentNotice::class), new Reference('clock')], $container->getDefinition(IncidentNoticeCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(IncidentNoticeCommand::class)->hasTag('console.command'));
        self::assertFalse($container->hasAlias(NoticeOrganisationSource::class));
    }

    public function testWithEveryPartTheDraftsAndTheIdentityStay(): void
    {
        $container = $this->load();
        $this->services($container, ['database_connection', 'clock', 'twig', 'translator', SiteIdentity::class]);
        new PrivacyServicesPass()->process($container);

        foreach ([IncidentNoticeCommand::class, TwigIncidentNotice::class, IncidentNotices::class, SiteIdentityOrganisation::class] as $id) {
            self::assertTrue($container->hasDefinition($id), $id);
        }

        self::assertSame(SiteIdentityOrganisation::class, (string) $container->getAlias(NoticeOrganisationSource::class));
        self::assertEquals([new Reference(SiteIdentity::class)], $container->getDefinition(SiteIdentityOrganisation::class)->getArguments());
    }

    public function testWithoutTheIdentityOfTheSiteTheRubricsAreLeftToComplete(): void
    {
        $container = $this->load();
        $this->services($container, ['database_connection', 'clock', 'twig', 'translator']);
        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasAlias(NoticeOrganisationSource::class));
        self::assertFalse($container->hasDefinition(SiteIdentityOrganisation::class));
        self::assertTrue($container->hasDefinition(IncidentNoticeCommand::class));
    }

    public function testAnOrganisationOfTheApplicationIsKept(): void
    {
        $container = $this->load();
        $this->services($container, ['database_connection', 'clock', 'twig', 'translator', 'app.organisation', SiteIdentity::class]);
        $container->setAlias(NoticeOrganisationSource::class, 'app.organisation');
        new PrivacyServicesPass()->process($container);

        self::assertSame('app.organisation', (string) $container->getAlias(NoticeOrganisationSource::class));
        self::assertFalse($container->hasDefinition(SiteIdentityOrganisation::class));
    }

    public function testTheDraftsNeedTwigTheTranslatorAClockAndTheRegister(): void
    {
        foreach ([
            'without Twig' => ['database_connection', 'clock', 'translator'],
            'without the translator' => ['database_connection', 'clock', 'twig'],
            'without a clock' => ['database_connection', 'twig', 'translator'],
            'without the register' => ['clock', 'twig', 'translator'],
        ] as $case => $present) {
            $container = $this->load();
            $this->services($container, $present);
            new PrivacyServicesPass()->process($container);

            self::assertFalse($container->hasDefinition(IncidentNoticeCommand::class), $case);
        }

        $twigless = $this->load();
        $this->services($twigless, ['database_connection', 'clock', 'translator']);
        new PrivacyServicesPass()->process($twigless);
        self::assertFalse($twigless->hasDefinition(TwigIncidentNotice::class));
    }

    /**
     * Nothing the command is built from can send: no mailer, notifier, bus or HTTP client among its services.
     */
    public function testTheCommandReachesNoTransport(): void
    {
        $container = $this->load(['kernel.secret' => 'secret']);
        $externals = [
            'database_connection' => DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            'clock' => new MockClock(),
            'twig' => NoticeFixtures::twig(),
            'translator' => new Translator('fr'),
            SiteIdentity::class => new SiteIdentity(['publisher_name' => 'Atelier Fictif inc.']),
        ];

        foreach (array_keys($externals) as $id) {
            $container->register($id)->setSynthetic(true)->setPublic(true);
        }

        new PrivacyServicesPass()->process($container);
        $reached = $this->reached($container, IncidentNoticeCommand::class);
        sort($reached);
        self::assertSame([
            SiteIdentity::class,
            IncidentRegister::class,
            NoticeOrganisationSource::class,
            IncidentNotices::class,
            IncidentNoticeCommand::class,
            DbalIncidentRegister::class,
            SiteIdentityOrganisation::class,
            TwigIncidentNotice::class,
            'clock',
            'database_connection',
            'twig',
        ], $reached);

        foreach ($reached as $id) {
            self::assertDoesNotMatchRegularExpression('/mail|notif|messenger|bus|http|transport|logger/i', $id);
        }

        $container->getDefinition(IncidentNoticeCommand::class)->setPublic(true);
        $container->compile(true);

        foreach ($externals as $id => $service) {
            $container->set($id, $service);
        }

        self::assertInstanceOf(IncidentNoticeCommand::class, $container->get(IncidentNoticeCommand::class));
    }

    /**
     * @param list<string> $seen
     *
     * @return list<string> the services the definition refers to, directly or through others
     */
    private function reached(ContainerBuilder $container, string $id, array $seen = []): array
    {
        $id = $container->hasAlias($id) ? (string) $container->getAlias($id) : $id;
        $seen[] = $id;

        if (!$container->hasDefinition($id)) {
            return $seen;
        }

        foreach ($this->references($container->getDefinition($id)->getArguments()) as $reference) {
            $next = (string) $reference;

            if (!\in_array($next, $seen, true)) {
                $seen[] = $next;
                $seen = $this->reached($container, $next, $seen);
            }
        }

        return array_values(array_unique($seen));
    }

    /**
     * @param array<mixed> $arguments
     *
     * @return list<Reference>
     */
    private function references(array $arguments): array
    {
        $references = [];

        foreach ($arguments as $argument) {
            if ($argument instanceof Reference) {
                $references[] = $argument;
            } elseif ($argument instanceof Definition) {
                $references = [...$references, ...$this->references($argument->getArguments())];
            } elseif ($argument instanceof ArgumentInterface) {
                $references = [...$references, ...$this->references($argument->getValues())];
            } elseif (\is_array($argument)) {
                $references = [...$references, ...$this->references($argument)];
            }
        }

        return $references;
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
     * @param array<string, string> $parameters
     */
    private function load(array $parameters = []): ContainerBuilder
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
        $bundle->loadExtension(new Processor()->processConfiguration($configuration, [[]]), new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__), $container);

        return $container;
    }
}
