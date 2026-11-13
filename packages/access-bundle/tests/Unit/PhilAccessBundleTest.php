<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit;

use DateInterval;
use Override;
use Phil\AccessBundle\Application\AccessGroups;
use Phil\AccessBundle\Application\Contract\CurrentMember;
use Phil\AccessBundle\Application\Contract\ModelContributor;
use Phil\AccessBundle\Application\Items;
use Phil\AccessBundle\Application\NoCurrentMember;
use Phil\AccessBundle\Application\Spaces;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\Status\EntryPoint;
use Phil\AccessBundle\Domain\Status\Visibility;
use Phil\AccessBundle\Domain\ValueObject\AccessGroup;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\AccessBundle\Infrastructure\Console\PurgeCommand;
use Phil\AccessBundle\Infrastructure\DependencyInjection\AccessServices;
use Phil\AccessBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\AccessBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\AccessBundle\Infrastructure\EasyAdmin\EasyAdminAccessVoter;
use Phil\AccessBundle\Infrastructure\Http\ViewAsController;
use Phil\AccessBundle\Infrastructure\Security\AccessVoter;
use Phil\AccessBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\AccessBundle\Infrastructure\Twig\AccessExtension;
use Phil\AccessBundle\Infrastructure\Twig\Components\AccessSummaryComponent;
use Phil\AccessBundle\Infrastructure\Twig\Components\ViewAsBannerComponent;
use Phil\AccessBundle\Infrastructure\Twig\KitComponents;
use Phil\AccessBundle\PhilAccessBundle;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Dbal\DbalTupleStore;
use Phil\ResourcePermissions\Dbal\TupleSchemaListener;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

#[CoversClass(PhilAccessBundle::class)]
#[CoversClass(AccessServices::class)]
#[CoversClass(CsrfProtectionPass::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilAccessBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_access', $this->extension()->getAlias());
        self::assertSame(DbalTupleStore::class, (string) $container->getAlias(TupleStore::class));
        $store = $container->getDefinition(DbalTupleStore::class);
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), new Reference('clock'), 'phil_relation_tuple'], $store->getArguments());
        self::assertSame([['event' => 'postGenerateSchema']], $container->getDefinition(TupleSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame([32, 500, 200, 1000], $container->getDefinition(Budget::class)->getArguments());
        self::assertEquals([[], new Reference(SharingLevels::class)], \array_slice($container->getDefinition(Model::class)->getArguments(), 0, 2));
        self::assertSame([SharingLevels::DEFAULT], $container->getDefinition(SharingLevels::class)->getArguments());
        self::assertSame(Visibility::Owner, $container->getDefinition(Items::class)->getArgument(4));
        self::assertSame('main', $container->getDefinition(Spaces::class)->getArgument(2));
        self::assertSame('/', $container->getDefinition(ViewAsController::class)->getArgument(2));
        self::assertTrue($container->getDefinition(ViewAsController::class)->hasTag('controller.service_arguments'));
        self::assertSame([], $container->getDefinition(AccessGroups::class)->getArgument(3));
        self::assertSame(NoCurrentMember::class, (string) $container->getAlias(CurrentMember::class));
        self::assertTrue($container->getDefinition(PurgeCommand::class)->isAutoconfigured());
        self::assertFalse($container->hasDefinition(AccessVoter::class));
        self::assertFalse($container->hasDefinition(EasyAdminAccessVoter::class));
        self::assertFalse($container->hasDefinition(AccessExtension::class));
        self::assertFalse($container->hasDefinition(KitComponents::class));
        self::assertArrayHasKey(ModelContributor::class, $container->getAutoconfiguredInstanceof());
        self::assertTrue($container->getAutoconfiguredInstanceof()[ModelContributor::class]->hasTag(AccessServices::CONTRIBUTOR_TAG));
        $contributors = $container->getDefinition(Model::class)->getArgument(2);
        self::assertInstanceOf(TaggedIteratorArgument::class, $contributors);
        self::assertSame(AccessServices::CONTRIBUTOR_TAG, $contributors->getTag());
    }

    public function testAFullConfiguration(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'item_kinds' => ['page'],
            'sharing_levels' => ['edit', 'read'],
            'default_visibility' => 'members',
            'space' => 'club',
            'groups' => ['supporters' => ['entry_points' => ['purchase', 'invitation'], 'duration' => 'P1M'], 'all' => ['entry_points' => ['default']]],
            'budget' => ['max_depth' => 5, 'max_reads' => 6, 'max_fan_out' => 7, 'max_candidates' => 8],
            'current_member' => 'app.member',
            'view_as' => ['redirect' => '/admin'],
            'easyadmin' => ['actions' => ['edit' => 'edit']],
            'kit_subdirectory' => 'kit',
        ]], ['SecurityBundle' => true, 'EasyAdminBundle' => true, 'TwigBundle' => true, 'TwigComponentBundle' => true]);

        self::assertSame(InMemoryTupleStore::class, $container->getDefinition(TupleStore::class)->getClass());
        self::assertFalse($container->hasDefinition(TupleSchemaListener::class));
        self::assertSame([5, 6, 7, 8], $container->getDefinition(Budget::class)->getArguments());
        self::assertSame(Visibility::Members, $container->getDefinition(Items::class)->getArgument(4));
        self::assertSame('app.member', (string) $container->getAlias(CurrentMember::class));
        self::assertSame('/admin', $container->getDefinition(ViewAsController::class)->getArgument(2));
        self::assertSame(['edit' => 'edit'], $container->getDefinition(EasyAdminAccessVoter::class)->getArgument(2));
        self::assertTrue($container->getDefinition(EasyAdminAccessVoter::class)->hasTag('security.voter'));
        self::assertTrue($container->getDefinition(AccessVoter::class)->hasTag('security.voter'));
        self::assertTrue($container->getDefinition(AccessExtension::class)->isAutoconfigured());
        self::assertSame(['kit'], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertTrue($container->getDefinition(ViewAsBannerComponent::class)->isAutoconfigured());
        self::assertTrue($container->getDefinition(AccessSummaryComponent::class)->isAutoconfigured());

        $groups = $container->getDefinition(AccessGroups::class)->getArgument(3);
        self::assertIsArray($groups);
        self::assertCount(2, $groups);
        self::assertInstanceOf(Definition::class, $groups[0]);
        self::assertSame([AccessGroup::class, 'of'], $groups[0]->getFactory());
        self::assertSame('supporters', $groups[0]->getArgument(0));
        self::assertSame([EntryPoint::Purchase, EntryPoint::Invitation], $groups[0]->getArgument(1));
        self::assertEquals(new Definition(DateInterval::class, ['P1M']), $groups[0]->getArgument(2));
        self::assertInstanceOf(Definition::class, $groups[1]);
        self::assertNull($groups[1]->getArgument(2));
    }

    public function testTheMemberOfTheTokenWithSecurity(): void
    {
        $container = $this->load([], ['SecurityBundle' => true]);

        self::assertSame(TokenCurrentMember::class, (string) $container->getAlias(CurrentMember::class));
        self::assertEquals([new Reference('security.token_storage')], $container->getDefinition(TokenCurrentMember::class)->getArguments());
    }

    public function testTheDefaultActionsOfEasyAdmin(): void
    {
        $container = $this->load([], ['EasyAdminBundle' => true]);

        self::assertSame(['detail' => 'view', 'edit' => 'edit', 'delete' => 'manage'], $container->getDefinition(EasyAdminAccessVoter::class)->getArgument(2));
        self::assertSame(['detail' => 'read'], AccessServices::defaultActions(SharingLevels::of(['write', 'read'])));
    }

    public function testInvalidConfigurations(): void
    {
        foreach ([
            [['store' => 'redis']],
            [['default_visibility' => 'friends']],
            [['groups' => ['g' => ['entry_points' => ['magic']]]]],
            [['groups' => ['g' => ['entry_points' => []]]]],
            [['budget' => ['max_depth' => 0]]],
            [['connection' => '']],
        ] as $config) {
            try {
                $this->load($config);
                self::fail('The configuration should be refused.');
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidAccess::class);

        $this->load([['sharing_levels' => ['owner']]]);
    }

    public function testTheContainerCompilesAndBuildsTheModel(): void
    {
        $container = $this->load([['store' => 'memory', 'item_kinds' => ['page']]]);
        $container->register('clock', MockClock::class)->setPublic(true);
        $container->register('request_stack', RequestStack::class);
        $container->register('security.csrf.token_manager', CsrfTokenManager::class);
        $container->register('folders', FolderContributor::class)->setAutoconfigured(true);
        new PhilAccessBundle()->build($container);
        $container->compile(true);

        $model = $container->get(Model::class);
        self::assertInstanceOf(Model::class, $model);
        self::assertTrue($model->hasRelation('page', 'view'));
        self::assertTrue($model->hasRelation('folder', 'viewer'));
    }

    public function testTheCsrfProtectionIsRequired(): void
    {
        $container = new ContainerBuilder();
        $container->register(ViewAsController::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil/access-bundle checks the forms of "view as this member"');

        new CsrfProtectionPass(ViewAsController::class, ViewAsController::TOKEN_ID)->process($container);
    }

    public function testTheCsrfPassLeavesOtherContainersAlone(): void
    {
        $without = new ContainerBuilder();
        new CsrfProtectionPass(ViewAsController::class, ViewAsController::TOKEN_ID)->process($without);
        $with = new ContainerBuilder();
        $with->register(ViewAsController::class);
        $with->register(CsrfProtectionPass::TOKEN_MANAGER);
        new CsrfProtectionPass(ViewAsController::class, ViewAsController::TOKEN_ID)->process($with);

        self::assertFalse($without->hasDefinition(ViewAsController::class));
    }

    public function testTheKitSubdirectory(): void
    {
        $pass = new KitSubdirectoryPass();
        $none = new ContainerBuilder();
        $pass->process($none);
        self::assertFalse($none->hasDefinition(KitComponents::class));

        $toolkit = new ContainerBuilder();
        $toolkit->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        $toolkit->register(KitComponents::class)->setArguments([null]);
        $pass->process($toolkit);
        self::assertSame('ui', $toolkit->getDefinition(KitComponents::class)->getArgument(0));

        $alone = new ContainerBuilder();
        $alone->register(KitComponents::class)->setArguments([null]);
        $pass->process($alone);
        self::assertSame('', $alone->getDefinition(KitComponents::class)->getArgument(0));

        $set = new ContainerBuilder();
        $set->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        $set->register(KitComponents::class)->setArguments(['kit']);
        $pass->process($set);
        self::assertSame('kit', $set->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testTheStatelessTokenIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        $extension = $this->extension();
        $this->assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => [ViewAsController::TOKEN_ID]]]], $container->getExtensionConfig('framework'));

        $alone = new ContainerBuilder();
        $extension->prepend($alone);
        self::assertSame([], $alone->getExtensionConfig('framework'));
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
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilAccessBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}

/**
 * A contributor that adds folders.
 */
final class FolderContributor implements ModelContributor
{
    #[Override]
    public function contribute(array $types): array
    {
        $types['folder'] = TypeDefinition::of('folder', ['viewer' => Rewrite::direct('member')]);

        return $types;
    }
}
