<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\TrashBundle\Application\Trash;
use Phil\TrashBundle\Application\TrashHandler;
use Phil\TrashBundle\Domain\Repository\TrashItemRepository;
use Phil\TrashBundle\Infrastructure\Console\PurgeTrashCommand;
use Phil\TrashBundle\Infrastructure\Doctrine\DoctrineTrashItemRepository;
use Phil\TrashBundle\PhilTrashBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilTrashBundle::class)]
final class PhilTrashBundleTest extends TestCase
{
    public function testTheServicesFollowTheConfiguration(): void
    {
        $container = $this->load([['retention' => 14, 'retention_by_kind' => ['page' => 90]]]);

        self::assertSame('phil_trash', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineTrashItemRepository::class, Trash::class, PurgeTrashCommand::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineTrashItemRepository::class)->getArguments());
        self::assertSame(DoctrineTrashItemRepository::class, (string) $container->getAlias(TrashItemRepository::class));
        self::assertTrue($container->getAlias(TrashItemRepository::class)->isPublic());
        self::assertEquals([
            new Reference(TrashItemRepository::class),
            new TaggedIteratorArgument('phil_trash.handler'),
            new Reference('clock'),
            14,
            ['page' => 90],
            new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(Trash::class)->getArguments());
        self::assertTrue($container->getDefinition(Trash::class)->isPublic());
        self::assertEquals([new Reference(Trash::class)], $container->getDefinition(PurgeTrashCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(PurgeTrashCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getAutoconfiguredInstanceof()[TrashHandler::class]->hasTag(PhilTrashBundle::HANDLER_TAG));
        self::assertSame('phil_trash.handler', PhilTrashBundle::HANDLER_TAG);
        self::assertSame([30, []], \array_slice($this->load([])->getDefinition(Trash::class)->getArguments(), 3, 2));
    }

    public function testRetentionIsAtLeastADay(): void
    {
        foreach ([['retention' => 0], ['retention_by_kind' => ['page' => 0]]] as $config) {
            try {
                $this->load([$config]);
                self::fail('A retention of 0 days was accepted.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('Should be greater than or equal to 1', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheEntityIsMappedWithDoctrine(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        /** @var array{orm: array{mappings: array<string, array<string, mixed>>}} $doctrine */
        $doctrine = $container->getExtensionConfig('doctrine')[0];
        $mapping = $doctrine['orm']['mappings']['PhilTrashBundle'];
        self::assertSame(['attribute', false, 'Phil\TrashBundle\Domain\Model'], [$mapping['type'] ?? null, $mapping['is_bundle'] ?? null, $mapping['prefix'] ?? null]);
        self::assertIsString($mapping['dir'] ?? null);
        self::assertStringEndsWith('/src/Domain/Model', $mapping['dir']);

        $without = new ContainerBuilder();
        $without->registerExtension($extension);

        $extension->prepend($without);
        self::assertFalse($without->hasExtension('doctrine'));
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
        $extension = new PhilTrashBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
