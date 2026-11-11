<?php

declare(strict_types=1);

namespace Phil\MigrationBundle\Tests\Unit;

use Doctrine\Migrations\Migrator;
use Doctrine\Migrations\Version\MigrationFactory;
use Phil\Migration\Announcements;
use Phil\Migration\Announcer;
use Phil\Migration\AnnouncingMigrationFactory;
use Phil\Migration\AnnouncingMigrator;
use Phil\Migration\Channel;
use Phil\Migration\ChatterPublisher;
use Phil\Migration\Content;
use Phil\Migration\Publisher;
use Phil\MigrationBundle\Infrastructure\DependencyInjection\AnnouncementServices;
use Phil\MigrationBundle\Infrastructure\DependencyInjection\AnnouncingMigrationsPass;
use Phil\MigrationBundle\PhilMigrationBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Notifier\ChatterInterface;

#[CoversClass(PhilMigrationBundle::class)]
#[CoversClass(AnnouncementServices::class)]
#[CoversClass(AnnouncingMigrationsPass::class)]
final class PhilMigrationBundleTest extends TestCase
{
    public function testWithoutChannelsTheMigrationsStillAnnounce(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_migration', $this->extension()->getAlias());
        self::assertSame(['service_container', Announcements::class], array_keys($container->getDefinitions()));

        $container = $this->withDependencyFactory($container, ['custom.factory']);
        new AnnouncingMigrationsPass()->process($container);

        $factory = $container->getDefinition(AnnouncingMigrationsPass::FACTORY_ID);
        self::assertSame([AnnouncingMigrationFactory::class, ['custom.factory', null, 0]], [$factory->getClass(), $factory->getDecoratedService()]);
        self::assertEquals([new Reference(AnnouncingMigrationsPass::FACTORY_ID.'.inner'), new Reference(Announcements::class)], $factory->getArguments());
        self::assertFalse($container->hasDefinition(AnnouncingMigrationsPass::MIGRATOR_ID));
        self::assertCount(5, $container->getDefinition(AnnouncingMigrationsPass::DEPENDENCY_FACTORY)->getMethodCalls());
    }

    public function testChannelsAreAnnouncedThroughTheChatter(): void
    {
        $container = $this->load(['channels' => [
            'discord_alice' => ['locale' => 'fr', 'title' => '# Version{counter}', 'continued' => '## Suite{counter}'],
            'news' => ['transport' => 'discord_news', 'content' => 'news', 'heading' => '# {title}', 'link' => 'Voir {url}', 'max_length' => 100],
        ]]);

        self::assertSame(['service_container', Announcements::class, Publisher::class, Announcer::class], array_keys($container->getDefinitions()));
        self::assertSame(ChatterPublisher::class, $container->getDefinition(Publisher::class)->getClass());
        self::assertSame(ChatterPublisher::class, $container->getDefinition(Publisher::class)->getClass());
        self::assertEquals([new Reference(ChatterInterface::class)], $container->getDefinition(Publisher::class)->getArguments());

        [$channels, $publisher, $logger] = $container->getDefinition(Announcer::class)->getArguments();
        self::assertEquals([new Reference(Publisher::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], [$publisher, $logger]);
        self::assertIsArray($channels);
        self::assertInstanceOf(Definition::class, $channels[0]);
        self::assertInstanceOf(Definition::class, $channels[1]);
        self::assertSame([Channel::class, Channel::class], [$channels[0]->getClass(), $channels[1]->getClass()]);
        self::assertEquals(['discord_alice', 'fr', Content::Notes, '# Version{counter}', '## Suite{counter}', '{title}', '{url}', 2000], $channels[0]->getArguments());
        self::assertEquals(['discord_news', '%kernel.default_locale%', Content::News, '', '', '# {title}', 'Voir {url}', 100], $channels[1]->getArguments());
        self::assertSame([100, ['notes', 'news']], [AnnouncementServices::MIN_LENGTH, AnnouncementServices::CONTENTS]);

        $container = $this->withDependencyFactory($container, [Migrator::class => 'custom.migrator']);
        new AnnouncingMigrationsPass()->process($container);

        $migrator = $container->getDefinition(AnnouncingMigrationsPass::MIGRATOR_ID);
        self::assertSame([AnnouncingMigrator::class, ['custom.migrator', null, 0]], [$migrator->getClass(), $migrator->getDecoratedService()]);
        self::assertEquals([new Reference(AnnouncingMigrationsPass::MIGRATOR_ID.'.inner'), new Reference(Announcements::class), new Reference(Announcer::class)], $migrator->getArguments());

        $factory = $container->getDefinition(AnnouncingMigrationsPass::FACTORY_ID);
        self::assertNull($factory->getDecoratedService());
        $made = $factory->getArgument(0);
        self::assertInstanceOf(Definition::class, $made);
        self::assertEquals([MigrationFactory::class, [new Reference(AnnouncingMigrationsPass::DEPENDENCY_FACTORY), 'getMigrationFactory']], [$made->getClass(), $made->getFactory()]);
        self::assertEquals(['setDefinition', [MigrationFactory::class, new ServiceClosureArgument(new Reference(AnnouncingMigrationsPass::FACTORY_ID))]], $container->getDefinition(AnnouncingMigrationsPass::DEPENDENCY_FACTORY)->getMethodCalls()[5]);
    }

    public function testTheMigratorDoctrineMakesIsWrapped(): void
    {
        $container = $this->withDependencyFactory($this->load(['channels' => ['alice' => []]]), ['doctrine.migrations.migrations_factory']);
        new AnnouncingMigrationsPass()->process($container);

        [$made, $announcements, $announcer] = $container->getDefinition(AnnouncingMigrationsPass::MIGRATOR_ID)->getArguments();
        self::assertEquals([new Reference(Announcements::class), new Reference(Announcer::class)], [$announcements, $announcer]);
        self::assertCount(3, $container->getDefinition(AnnouncingMigrationsPass::MIGRATOR_ID)->getArguments());
        self::assertInstanceOf(Definition::class, $made);
        self::assertEquals([Migrator::class, [new Reference(AnnouncingMigrationsPass::DEPENDENCY_FACTORY), 'getMigrator']], [$made->getClass(), $made->getFactory()]);
        self::assertSame('doctrine.migrations.migrations_factory', $container->getDefinition(AnnouncingMigrationsPass::FACTORY_ID)->getDecoratedService()[0] ?? null);
        self::assertEquals(['setDefinition', [Migrator::class, new ServiceClosureArgument(new Reference(AnnouncingMigrationsPass::MIGRATOR_ID))]], $container->getDefinition(AnnouncingMigrationsPass::DEPENDENCY_FACTORY)->getMethodCalls()[5]);
    }

    public function testWithoutDoctrineMigrationsNothingIsWrapped(): void
    {
        $container = $this->load(['channels' => ['alice' => []]]);
        new AnnouncingMigrationsPass()->process($container);

        self::assertFalse($container->hasDefinition(AnnouncingMigrationsPass::FACTORY_ID));

        $built = new ContainerBuilder();
        new PhilMigrationBundle()->build($built);
        self::assertCount(1, array_filter($built->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof AnnouncingMigrationsPass));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'an unknown content' => [['channels' => ['alice' => ['content' => 'posts']]], 'content'];
        yield 'too short' => [['channels' => ['alice' => ['max_length' => 99]]], 'max_length'];
        yield 'no locale' => [['channels' => ['alice' => ['locale' => '']]], 'locale'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testTheShortestMessage(): void
    {
        $channels = $this->load(['channels' => ['alice' => ['max_length' => 100]]])->getDefinition(Announcer::class)->getArgument(0);
        self::assertIsArray($channels);
        self::assertInstanceOf(Definition::class, $channels[0]);
        self::assertSame(100, $channels[0]->getArgument(7));
    }

    /**
     * The dependency factory of DoctrineMigrationsBundle, with the factory — and maybe the migrator — it was given.
     *
     * @param array<int|string, string> $services the factory first, then the migrator by its interface
     */
    private function withDependencyFactory(ContainerBuilder $container, array $services): ContainerBuilder
    {
        $dependencies = $container->register(AnnouncingMigrationsPass::DEPENDENCY_FACTORY, stdClass::class);
        $dependencies->addMethodCall('setConfiguration', ['configuration']);

        foreach ($services as $service => $id) {
            $dependencies->addMethodCall('setDefinition', [\is_int($service) ? MigrationFactory::class : $service, new ServiceClosureArgument(new Reference($id))]);
        }

        $dependencies->addMethodCall('setService', [MigrationFactory::class, new ServiceClosureArgument(new Reference('not.a.definition'))]);
        $dependencies->addMethodCall('setDefinition', [MigrationFactory::class, 'not a closure']);
        $dependencies->addMethodCall('setDefinition', [Migrator::class, new ServiceClosureArgument(new Definition())]);

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilMigrationBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
