<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\EditLockBundle\Application\BlockSaves;
use Phil\EditLockBundle\Application\Broadcaster;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Application\NullBroadcaster;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Infrastructure\DependencyInjection\EditLockServices;
use Phil\EditLockBundle\Infrastructure\Doctrine\DoctrineLeaseRepository;
use Phil\EditLockBundle\Infrastructure\Http\ClientConfig;
use Phil\EditLockBundle\Infrastructure\Http\EditLockController;
use Phil\EditLockBundle\Infrastructure\Http\EditLockRequest;
use Phil\EditLockBundle\Infrastructure\Memory\InMemoryLeaseRepository;
use Phil\EditLockBundle\Infrastructure\Mercure\MercureBroadcaster;
use Phil\EditLockBundle\Infrastructure\Mercure\TurboStreams;
use Phil\EditLockBundle\PhilEditLockBundle;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\MercureBundle\DependencyInjection\MercureExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\HubInterface;

#[CoversClass(PhilEditLockBundle::class)]
#[CoversClass(EditLockServices::class)]
final class PhilEditLockBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_edit_lock', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineLeaseRepository::class, NullBroadcaster::class, TurboStreams::class, EditLocks::class, BlockSaves::class, EditLockRequest::class, EditLockController::class, ClientConfig::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DoctrineLeaseRepository::class)->getArguments());
        self::assertSame([DoctrineLeaseRepository::class, true], [(string) $container->getAlias(LeaseRepository::class), $container->getAlias(LeaseRepository::class)->isPublic()]);
        self::assertSame(NullBroadcaster::class, (string) $container->getAlias(Broadcaster::class));
        self::assertEquals([new Reference(LeaseRepository::class), new Reference('clock'), 30, null, new Reference(Broadcaster::class)], $container->getDefinition(EditLocks::class)->getArguments());
        self::assertEquals([
            new Reference(EditLocks::class),
            new Reference(Broadcaster::class),
            new Reference(Ujo::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
        ], $container->getDefinition(BlockSaves::class)->getArguments());
        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(EditLockRequest::class)->getArguments());
        self::assertEquals([new Reference(EditLocks::class), new Reference(BlockSaves::class), new Reference('clock'), new Reference(EditLockRequest::class), null, false], $container->getDefinition(EditLockController::class)->getArguments());
        self::assertTrue($container->getDefinition(EditLockController::class)->hasTag('controller.service_arguments'));
        self::assertEquals([30, new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), null, 'urn:phil:edit-lock:', new Reference(EditLockRequest::class)], $container->getDefinition(ClientConfig::class)->getArguments());

        foreach ([EditLocks::class, BlockSaves::class, EditLockController::class, ClientConfig::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'seconds' => 60,
            'store' => 'memory',
            'holder_provider' => 'app.holders',
            'policy' => 'app.policy',
            'block_store' => 'app.blocks',
            'mercure' => ['enabled' => true, 'hub' => 'mercure.hub.other', 'topic_prefix' => 'app:', 'private' => false],
        ]);

        self::assertSame(['service_container', InMemoryLeaseRepository::class, MercureBroadcaster::class, TurboStreams::class, EditLocks::class, BlockSaves::class, EditLockRequest::class, EditLockController::class, ClientConfig::class], array_keys($container->getDefinitions()));
        self::assertSame([InMemoryLeaseRepository::class, true], [(string) $container->getAlias(LeaseRepository::class), $container->getAlias(LeaseRepository::class)->isPublic()]);
        self::assertSame(MercureBroadcaster::class, (string) $container->getAlias(Broadcaster::class));
        self::assertEquals([
            new Reference('mercure.hub.other'),
            new Reference(TurboStreams::class),
            'app:',
            false,
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(MercureBroadcaster::class)->getArguments());
        self::assertEquals([new Reference(LeaseRepository::class), new Reference('clock'), 60, new Reference('app.policy'), new Reference(Broadcaster::class)], $container->getDefinition(EditLocks::class)->getArguments());
        self::assertEquals(new Reference('app.blocks'), $container->getDefinition(BlockSaves::class)->getArgument(3));
        self::assertEquals([new Reference('app.holders'), true], \array_slice($container->getDefinition(EditLockController::class)->getArguments(), 4));
        self::assertEquals([60, new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference('mercure.hub.other'), 'app:', new Reference(EditLockRequest::class)], $container->getDefinition(ClientConfig::class)->getArguments());
        self::assertFalse($this->load(['holder_provider' => 'app.holders'])->getDefinition(EditLockController::class)->getArgument(5));
        self::assertFalse($this->load(['policy' => 'app.policy'])->getDefinition(EditLockController::class)->getArgument(5));
        self::assertEquals([new Reference('doctrine.dbal.locks_connection')], $this->load(['store' => 'doctrine', 'connection' => 'locks'])->getDefinition(DoctrineLeaseRepository::class)->getArguments());
        self::assertSame([5, 3600], [$this->load(['seconds' => 5])->getDefinition(EditLocks::class)->getArgument(2), $this->load(['seconds' => 3600])->getDefinition(EditLocks::class)->getArgument(2)]);
    }

    public function testMercureFollowsMercureBundle(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new MercureExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['mercure' => ['enabled' => true]]], $container->getExtensionConfig('phil_edit_lock'));
        $on = new ContainerBuilder();
        $extension->load([...$container->getExtensionConfig('phil_edit_lock'), []], $on);
        self::assertSame(MercureBroadcaster::class, (string) $on->getAlias(Broadcaster::class));
        self::assertEquals([new Reference(HubInterface::class), new Reference(TurboStreams::class), 'urn:phil:edit-lock:', true, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $on->getDefinition(MercureBroadcaster::class)->getArguments());

        $off = new ContainerBuilder();
        $extension->load([...$container->getExtensionConfig('phil_edit_lock'), ['mercure' => ['enabled' => false]]], $off);
        self::assertSame(NullBroadcaster::class, (string) $off->getAlias(Broadcaster::class));
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([['seconds' => 4], ['seconds' => 3601], ['store' => 'redis'], ['connection' => ''], ['mercure' => ['hub' => '']]] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_edit_lock', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheMappingAndTheAssetsArePrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilEditLockBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\EditLockBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_edit_lock']]], ['asset_mapper' => ['paths' => [
            PhilEditLockBundle::CONTROLLERS_DIR => '@phil/edit-lock-bundle',
            PhilEditLockBundle::STYLES_DIR => 'phil-edit-lock',
        ]]]], $container->getExtensionConfig('framework'));
        self::assertFileExists(PhilEditLockBundle::CONTROLLERS_DIR.'/collaboration_controller.js');
        self::assertFileExists(PhilEditLockBundle::STYLES_DIR.'/edit-lock.css');

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([[], [], []], [$bare->getExtensionConfig('doctrine'), $bare->getExtensionConfig('framework'), $bare->getExtensionConfig('phil_edit_lock')]);
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
        $extension = new PhilEditLockBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
