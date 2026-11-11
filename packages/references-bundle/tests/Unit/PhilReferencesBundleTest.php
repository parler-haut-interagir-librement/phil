<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Unit;

use Phil\ReferencesBundle\Application\ReferenceCollector;
use Phil\ReferencesBundle\Application\References;
use Phil\ReferencesBundle\Application\ReferenceTarget;
use Phil\ReferencesBundle\Domain\Repository\UsageRepository;
use Phil\ReferencesBundle\Infrastructure\Console\CheckReferencesCommand;
use Phil\ReferencesBundle\Infrastructure\Doctrine\DbalUsageRepository;
use Phil\ReferencesBundle\Infrastructure\Doctrine\ReferenceTrackingListener;
use Phil\ReferencesBundle\Infrastructure\Doctrine\UsageSchemaListener;
use Phil\ReferencesBundle\PhilReferencesBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilReferencesBundle::class)]
final class PhilReferencesBundleTest extends TestCase
{
    public function testTheServicesFollowTheConfiguration(): void
    {
        $container = $this->load([['connection' => 'content', 'block_deletion' => false]]);
        $connection = new Reference('doctrine.dbal.content_connection');

        self::assertSame('phil_references', $this->extension()->getAlias());
        self::assertSame(['service_container', DbalUsageRepository::class, References::class, UsageSchemaListener::class, ReferenceTrackingListener::class, CheckReferencesCommand::class], array_keys($container->getDefinitions()));
        self::assertEquals([$connection], $container->getDefinition(DbalUsageRepository::class)->getArguments());
        self::assertSame(DbalUsageRepository::class, (string) $container->getAlias(UsageRepository::class));
        self::assertTrue($container->getAlias(UsageRepository::class)->isPublic());
        self::assertEquals([new Reference(UsageRepository::class), new TaggedIteratorArgument('phil_references.collector'), new TaggedIteratorArgument('phil_references.target')], $container->getDefinition(References::class)->getArguments());
        self::assertTrue($container->getDefinition(References::class)->isPublic());
        self::assertEquals([$connection], $container->getDefinition(UsageSchemaListener::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'content']], $container->getDefinition(UsageSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference(References::class), false], $container->getDefinition(ReferenceTrackingListener::class)->getArguments());
        self::assertSame([['event' => 'onFlush', 'connection' => 'content'], ['event' => 'postFlush', 'connection' => 'content'], ['event' => 'onClear', 'connection' => 'content']], $container->getDefinition(ReferenceTrackingListener::class)->getTag('doctrine.event_listener'));
        self::assertSame([['method' => 'reset']], $container->getDefinition(ReferenceTrackingListener::class)->getTag('kernel.reset'));
        self::assertEquals([new Reference(References::class)], $container->getDefinition(CheckReferencesCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(CheckReferencesCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getAutoconfiguredInstanceof()[ReferenceCollector::class]->hasTag(PhilReferencesBundle::COLLECTOR_TAG));
        self::assertTrue($container->getAutoconfiguredInstanceof()[ReferenceTarget::class]->hasTag(PhilReferencesBundle::TARGET_TAG));
        self::assertSame(['phil_references.collector', 'phil_references.target'], [PhilReferencesBundle::COLLECTOR_TAG, PhilReferencesBundle::TARGET_TAG]);
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalUsageRepository::class)->getArguments());
        self::assertTrue($container->getDefinition(ReferenceTrackingListener::class)->getArgument(1));
        self::assertFalse($this->load([['track_entities' => false]])->hasDefinition(ReferenceTrackingListener::class));
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
        $extension = new PhilReferencesBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
