<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Infrastructure;

use Phil\AccessBundle\Application\AccessGroups;
use Phil\AccessBundle\Application\AccessSubject;
use Phil\AccessBundle\Application\Contract\CurrentMember;
use Phil\AccessBundle\Application\Contract\ViewAsStore;
use Phil\AccessBundle\Application\ItemAccess;
use Phil\AccessBundle\Application\Items;
use Phil\AccessBundle\Application\Sharing;
use Phil\AccessBundle\Application\Spaces;
use Phil\AccessBundle\Application\ViewAs;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\AccessBundle\Infrastructure\Console\PurgeCommand;
use Phil\AccessBundle\Infrastructure\DependencyInjection\AccessServices;
use Phil\AccessBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\AccessBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\AccessBundle\Infrastructure\Http\ViewAsController;
use Phil\AccessBundle\Infrastructure\Security\AccessVoter;
use Phil\AccessBundle\Infrastructure\Session\SessionViewAsStore;
use Phil\AccessBundle\Infrastructure\Twig\AccessExtension;
use Phil\AccessBundle\Infrastructure\Twig\Components\AccessSummaryComponent;
use Phil\AccessBundle\Infrastructure\Twig\Components\ViewAsBannerComponent;
use Phil\AccessBundle\Infrastructure\Twig\KitComponents;
use Phil\AccessBundle\PhilAccessBundle;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Contract\RelationCheck;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Phil\ResourcePermissions\Dbal\TupleSchemaListener;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationWriter;
use Phil\ResourcePermissions\Tree\TreePermissions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * What each service of the bundle is given and whether the application may fetch it: every argument, tag and
 * visibility of the container the extension fills.
 */
#[CoversClass(AccessServices::class)]
#[CoversClass(PhilAccessBundle::class)]
final class AccessServicesWiringTest extends TestCase
{
    private const array BUNDLES = ['SecurityBundle' => true, 'EasyAdminBundle' => true, 'TwigBundle' => true, 'TwigComponentBundle' => true];

    public function testTheApplicationServices(): void
    {
        $container = $this->load([['item_kinds' => ['page']]]);
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $channel = [['channel' => 'phil_access']];

        self::assertEquals([new Reference('request_stack')], $container->getDefinition(SessionViewAsStore::class)->getArguments());
        self::assertFalse($container->getDefinition(SessionViewAsStore::class)->isPublic());
        self::assertTrue($container->getDefinition(Spaces::class)->isPublic());

        $viewAs = $container->getDefinition(ViewAs::class);
        self::assertEquals([new Reference(ViewAsStore::class), new Reference(CurrentMember::class), new Reference(Spaces::class), $logger, new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $viewAs->getArguments());
        self::assertSame($channel, $viewAs->getTag('monolog.logger'));
        self::assertTrue($viewAs->isPublic());

        self::assertEquals([new Reference(CurrentMember::class), new Reference(ViewAs::class)], $container->getDefinition(AccessSubject::class)->getArguments());

        $access = $container->getDefinition(ItemAccess::class);
        self::assertEquals([new Reference(RelationCheck::class), new Reference(AccessSubject::class), new Reference(SharingLevels::class), $logger], $access->getArguments());
        self::assertSame($channel, $access->getTag('monolog.logger'));
        self::assertTrue($access->isPublic());

        self::assertTrue($container->getDefinition(Items::class)->isPublic());
        self::assertTrue($container->getDefinition(AccessGroups::class)->isPublic());

        $sharing = $container->getDefinition(Sharing::class);
        self::assertEquals([new Reference(RelationWriter::class), new Reference(TupleStore::class), new Reference(SharingLevels::class), new Reference(AccessGroups::class), new Reference('clock')], $sharing->getArguments());
        self::assertTrue($sharing->isPublic());
    }

    public function testTheModelServicesOnDbal(): void
    {
        $container = $this->load([['item_kinds' => ['page']]]);

        self::assertTrue($container->getAlias(TupleStore::class)->isPublic());
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), new Reference(DbalTupleStore::class)], $container->getDefinition(TupleSchemaListener::class)->getArguments());
        $this->assertModelServices($container);
    }

    public function testTheModelServicesInMemory(): void
    {
        $container = $this->load([['store' => 'memory', 'item_kinds' => ['page']]]);

        $store = $container->getDefinition(TupleStore::class);
        self::assertSame(InMemoryTupleStore::class, $store->getClass());
        self::assertEquals([new Reference('clock')], $store->getArguments());
        self::assertTrue($store->isPublic());
        $this->assertModelServices($container);
    }

    public function testTheIntegrations(): void
    {
        $container = $this->load([['item_kinds' => ['page']]], self::BUNDLES);

        self::assertEquals([new Reference(ItemAccess::class), new Reference(SharingLevels::class), new Reference(ViewAs::class)], $container->getDefinition(AccessVoter::class)->getArguments());
        self::assertEquals([new Reference(ItemAccess::class), new Reference(Items::class), new Reference(AccessSubject::class), new Reference(ViewAs::class)], $container->getDefinition(AccessExtension::class)->getArguments());
        self::assertEquals([new Reference(ViewAs::class), new Reference(KitComponents::class), new Reference('router'), new Reference('security.csrf.token_manager')], $container->getDefinition(ViewAsBannerComponent::class)->getArguments());
        self::assertEquals([new Reference(ItemAccess::class), new Reference(Items::class), new Reference(Sharing::class), new Reference(SharingLevels::class), new Reference(KitComponents::class)], $container->getDefinition(AccessSummaryComponent::class)->getArguments());
        self::assertTrue($container->getDefinition(ViewAsController::class)->isPublic());
        self::assertEquals([new Reference(TupleStore::class)], $container->getDefinition(PurgeCommand::class)->getArguments());
    }

    public function testTheBundleAddsItsCompilerPasses(): void
    {
        $container = new ContainerBuilder();

        new PhilAccessBundle()->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getPasses());

        self::assertContains(CsrfProtectionPass::class, $passes);
        self::assertContains(KitSubdirectoryPass::class, $passes);
    }

    public function testTheDbalStoreIsAChoiceOfTheConfiguration(): void
    {
        $container = $this->load([['store' => 'dbal', 'item_kinds' => ['page']]]);

        self::assertTrue($container->hasDefinition(DbalTupleStore::class));
    }

    #[DataProvider('budgetEdges')]
    public function testTheBudgetOfAConfigurationIsBetweenOneAndOneHundredThousand(string $key): void
    {
        $this->load([['budget' => [$key => 1]]]);
        $this->load([['budget' => [$key => 100000]]]);

        foreach ([0, 100001] as $value) {
            try {
                $this->load([['budget' => [$key => $value]]]);
                self::fail(\sprintf('%s of %d should be refused.', $key, $value));
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function budgetEdges(): iterable
    {
        foreach (['max_depth', 'max_reads', 'max_fan_out', 'max_candidates'] as $key) {
            yield $key => [$key];
        }
    }

    private function assertModelServices(ContainerBuilder $container): void
    {
        self::assertEquals([new Reference(Model::class), new Reference(TupleStore::class), new Reference(Budget::class)], $container->getDefinition(RelationChecker::class)->getArguments());
        self::assertTrue($container->getAlias(RelationCheck::class)->isPublic());
        $writer = $container->getDefinition(RelationWriter::class);
        self::assertEquals([new Reference(Model::class), new Reference(TupleStore::class)], $writer->getArguments());
        self::assertTrue($writer->isPublic());
        self::assertTrue($container->hasDefinition(TreePermissions::class));
        $tree = $container->getDefinition(TreePermissions::class);
        self::assertEquals([new Reference(TupleStore::class)], $tree->getArguments());
        self::assertTrue($tree->isPublic());
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param array<string, bool>        $bundles
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = new PhilAccessBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load($configs, $container);

        return $container;
    }
}
