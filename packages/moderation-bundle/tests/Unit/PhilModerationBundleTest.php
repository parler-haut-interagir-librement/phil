<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit;

use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Phil\IncidentMode\Contract\LiftScheduler;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\Messenger\LiftExpiredIncidentsHandler;
use Phil\IncidentMode\Messenger\LiftIncidentHandler;
use Phil\IncidentMode\Messenger\MessengerLiftScheduler;
use Phil\IncidentMode\NoLiftScheduler;
use Phil\IncidentMode\Preset;
use Phil\IncidentMode\Presets;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Application\Contract\MemberProfiles;
use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Application\Contract\SandboxPolicy;
use Phil\ModerationBundle\Application\Contract\TrustedHiders;
use Phil\ModerationBundle\Application\Contract\WordScreen;
use Phil\ModerationBundle\Application\FirstPostSandbox;
use Phil\ModerationBundle\Application\ModerationTargets;
use Phil\ModerationBundle\Application\NoCurrentMember;
use Phil\ModerationBundle\Application\NoTrustedHiders;
use Phil\ModerationBundle\Application\NoWordScreen;
use Phil\ModerationBundle\Application\ProfileTarget;
use Phil\ModerationBundle\Application\Reasons;
use Phil\ModerationBundle\Application\ReportSurge;
use Phil\ModerationBundle\Application\Role;
use Phil\ModerationBundle\Application\Roles;
use Phil\ModerationBundle\Domain\Contract\ReportRepository;
use Phil\ModerationBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\ModerationBundle\Infrastructure\DependencyInjection\ModerationPass;
use Phil\ModerationBundle\Infrastructure\DependencyInjection\ModerationServices;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalReportRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationSchemaListener;
use Phil\ModerationBundle\Infrastructure\Http\AdminController;
use Phil\ModerationBundle\Infrastructure\Http\JournalController;
use Phil\ModerationBundle\Infrastructure\Http\ReportController;
use Phil\ModerationBundle\Infrastructure\IncidentMode\CurrentMemberRestrictions;
use Phil\ModerationBundle\Infrastructure\IncidentMode\IncidentPanel;
use Phil\ModerationBundle\Infrastructure\Privacy\ModerationPrivacy;
use Phil\ModerationBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\ModerationBundle\Infrastructure\Security\TokenCurrentModerator;
use Phil\ModerationBundle\Infrastructure\Twig\KitComponents;
use Phil\ModerationBundle\Infrastructure\Twig\ModerationExtension;
use Phil\ModerationBundle\PhilModerationBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilModerationBundle::class)]
#[CoversClass(ModerationServices::class)]
#[CoversClass(ModerationPass::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilModerationBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([], ['SecurityBundle' => self::class]);

        self::assertTrue($container->getParameter('phil_moderation.enabled'));
        self::assertSame('phil_moderation', $this->extension()->getAlias());
        self::assertSame(DbalReportRepository::class, $container->getDefinition(ReportRepository::class)->getClass());
        self::assertEquals(new Reference('doctrine.dbal.default_connection'), $container->getDefinition(ReportRepository::class)->getArgument(0));
        self::assertSame([['event' => 'postGenerateSchema']], $container->getDefinition(ModerationSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame([Reasons::DEFAULTS], $container->getDefinition(Reasons::class)->getArguments());
        self::assertSame(TokenCurrentMember::class, (string) $container->getAlias(CurrentMember::class));
        self::assertSame(TokenCurrentModerator::class, (string) $container->getAlias(CurrentModerator::class));
        self::assertSame(NoWordScreen::class, (string) $container->getAlias(WordScreen::class));
        self::assertSame(NoTrustedHiders::class, (string) $container->getAlias(TrustedHiders::class));
        self::assertSame(FirstPostSandbox::class, (string) $container->getAlias(SandboxPolicy::class));
        self::assertSame([], $container->getDefinition(FirstPostSandbox::class)->getArgument(1));
        self::assertSame([25, 3600], \array_slice($container->getDefinition(ReportSurge::class)->getArguments(), 2));
        self::assertEquals(new TaggedIteratorArgument(ModerationServices::TARGET_TAG), $container->getDefinition(ModerationTargets::class)->getArgument(0));
        self::assertArrayHasKey(ModerationTarget::class, $container->getAutoconfiguredInstanceof());
        self::assertTrue($container->getAutoconfiguredInstanceof()[ModerationTarget::class]->hasTag(ModerationServices::TARGET_TAG));

        $roles = $container->getDefinition(Roles::class)->getArgument(0);
        self::assertIsArray($roles);
        self::assertEquals([
            new Definition(Role::class, ['moderator', 'ROLE_MODERATOR', []]),
            new Definition(Role::class, ['administrator', 'ROLE_ADMIN', []]),
        ], $roles);

        $admin = $container->getDefinition(AdminController::class);
        self::assertSame(['@PhilModeration/layout.html.twig', 25, 'ROLE_ADMIN'], \array_slice($admin->getArguments(), 8, 3));
        self::assertEquals(new Reference(IncidentPanel::class), $admin->getArgument(11));
        self::assertFalse($admin->hasTag(EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER));
        self::assertTrue($admin->isPublic());
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertTrue($container->getDefinition(ModerationExtension::class)->hasTag('twig.runtime'));
        self::assertTrue($container->getDefinition(ModerationExtension::class)->hasTag('twig.attribute_extension'));
        self::assertEquals(new Reference(CurrentMemberRestrictions::class), $container->getDefinition(ModerationExtension::class)->getArgument(3));
        self::assertTrue($container->getDefinition(ModerationPrivacy::class)->hasTag(ModerationServices::EXPORTER_TAG));
        self::assertTrue($container->getDefinition(ModerationPrivacy::class)->hasTag(ModerationServices::ERASER_TAG));
        self::assertSame(MessengerLiftScheduler::class, (string) $container->getAlias(LiftScheduler::class));
        self::assertTrue($container->getDefinition(LiftIncidentHandler::class)->hasTag('messenger.message_handler'));
        self::assertSame('ROLE_ADMIN', $container->getParameter('phil_moderation.incident_role'));
        self::assertTrue($container->getDefinition(IncidentMode::class)->isPublic());
        self::assertCount(5, $this->presets($container)->all());
    }

    public function testTheConfiguration(): void
    {
        $container = $this->load([[
            'connection' => 'moderation',
            'reasons' => ['spam', 'scam'],
            'roles' => [['name' => 'keeper', 'attribute' => 'ROLE_KEEPER', 'types' => ['comment']]],
            'admin' => ['layout' => 'admin.html.twig', 'page_size' => 10],
            'journal' => ['page_size' => 20],
            'sandbox' => ['types' => ['forum.post']],
            'surge' => ['reports' => 5, 'within' => 600],
            'incident' => [
                'role' => 'ROLE_SUPER',
                'presets' => [['name' => 'quiet', 'label' => 'Quiet', 'restrictions' => ['new-posts'], 'default_minutes' => 30, 'maximum_minutes' => 120]],
                'without' => ['read-only', 'suspend-invitations'],
            ],
            'kit_subdirectory' => 'kit',
        ]], ['EasyAdminBundle' => EasyAdminBundle::class, 'SecurityBundle' => self::class]);

        self::assertEquals(new Reference('doctrine.dbal.moderation_connection'), $container->getDefinition(ReportRepository::class)->getArgument(0));
        self::assertSame([['spam', 'scam']], $container->getDefinition(Reasons::class)->getArguments());
        self::assertEquals([new Definition(Role::class, ['keeper', 'ROLE_KEEPER', ['comment']])], $container->getDefinition(Roles::class)->getArgument(0));
        self::assertSame(['forum.post'], $container->getDefinition(FirstPostSandbox::class)->getArgument(1));
        self::assertSame([5, 600], \array_slice($container->getDefinition(ReportSurge::class)->getArguments(), 2));
        self::assertSame(['admin.html.twig', 10, 'ROLE_SUPER'], \array_slice($container->getDefinition(AdminController::class)->getArguments(), 8, 3));
        self::assertTrue($container->getDefinition(AdminController::class)->hasTag(EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER));
        self::assertSame(['admin.html.twig', 20], \array_slice($container->getDefinition(JournalController::class)->getArguments(), 2));
        self::assertSame(['kit'], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertSame('ROLE_SUPER', $container->getParameter('phil_moderation.incident_role'));

        $presets = $this->presets($container);
        self::assertSame(['suspend-signups', 'suspend-new-messages', 'suspend-contributions', 'quiet'], array_map(static fn (Preset $preset): string => $preset->name, $presets->all()));
        self::assertSame(1_800, $presets->get('quiet')->defaultDuration->seconds);
        self::assertSame(7_200, $presets->get('quiet')->maximumDuration->seconds);
        self::assertSame(['new-posts'], $presets->get('quiet')->restrictionNames());
        self::assertSame('Quiet', $presets->get('quiet')->label);
    }

    public function testWithoutSecurityNobodyModerates(): void
    {
        $container = $this->load([], ['EasyAdminBundle' => EasyAdminBundle::class]);

        self::assertSame(NoCurrentMember::class, (string) $container->getAlias(CurrentMember::class));
        self::assertSame(NoCurrentMember::class, (string) $container->getAlias(CurrentModerator::class));
        self::assertFalse($container->hasDefinition(TokenCurrentMember::class));
        self::assertFalse($container->hasDefinition(AdminController::class));
        self::assertTrue($container->hasDefinition(ReportController::class));
        self::assertTrue($container->hasDefinition(JournalController::class));
    }

    public function testItCanBeDisabled(): void
    {
        $container = $this->load([['enabled' => false]]);

        self::assertFalse($container->getParameter('phil_moderation.enabled'));
        self::assertFalse($container->hasDefinition(ReportRepository::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'no reason' => [['reasons' => []]];
        yield 'no role' => [['roles' => []]];
        yield 'a role without an attribute' => [['roles' => [['name' => 'x']]]];
        yield 'a page too large' => [['admin' => ['page_size' => 201]]];
        yield 'an empty page' => [['journal' => ['page_size' => 0]]];
        yield 'a window too short' => [['surge' => ['within' => 59]]];
        yield 'a window too long' => [['surge' => ['within' => 86_401]]];
        yield 'no surge' => [['surge' => ['reports' => 0]]];
        yield 'a preset without restriction' => [['incident' => ['presets' => [['name' => 'q', 'label' => 'Q', 'restrictions' => [], 'default_minutes' => 1, 'maximum_minutes' => 1]]]]];
        yield 'an empty connection' => [['connection' => '']];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([$config]);
    }

    public function testTheProfilesBecomeATargetWhenTheApplicationGivesThem(): void
    {
        $container = $this->load([]);
        new ModerationPass()->process($container);
        self::assertFalse($container->hasDefinition(ProfileTarget::class));

        $container->register(MemberProfiles::class, MemberProfiles::class);
        new ModerationPass()->process($container);

        self::assertTrue($container->getDefinition(ProfileTarget::class)->hasTag(ModerationServices::TARGET_TAG));
        self::assertEquals(new Reference(MemberProfiles::class), $container->getDefinition(ProfileTarget::class)->getArgument(0));
    }

    public function testTheLiftingFallsBackOnTheSweepWithoutABus(): void
    {
        $container = $this->load([]);
        new ModerationPass()->process($container);

        self::assertSame(NoLiftScheduler::class, (string) $container->getAlias(LiftScheduler::class));
        self::assertFalse($container->hasDefinition(MessengerLiftScheduler::class));
        self::assertFalse($container->hasDefinition(LiftIncidentHandler::class));
        self::assertFalse($container->hasDefinition(LiftExpiredIncidentsHandler::class));

        $withBus = $this->load([]);
        $withBus->register('messenger.default_bus', stdClass::class);
        new ModerationPass()->process($withBus);

        self::assertSame(MessengerLiftScheduler::class, (string) $withBus->getAlias(LiftScheduler::class));
        self::assertTrue($withBus->hasDefinition(LiftIncidentHandler::class));
    }

    public function testTheKitIsNamedAfterTheToolkit(): void
    {
        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());

        $own = $this->load([['kit_subdirectory' => 'mine']]);
        $own->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($own);
        self::assertSame(['mine'], $own->getDefinition(KitComponents::class)->getArguments());

        $none = $this->load([]);
        new KitSubdirectoryPass()->process($none);
        self::assertSame([''], $none->getDefinition(KitComponents::class)->getArguments());

        $disabled = $this->load([['enabled' => false]]);
        new KitSubdirectoryPass()->process($disabled);
        self::assertFalse($disabled->hasDefinition(KitComponents::class));
    }

    public function testTheBundleAddsItsPasses(): void
    {
        $container = new ContainerBuilder();
        new PhilModerationBundle()->build($container);
        $classes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(ModerationPass::class, $classes);
        self::assertContains(KitSubdirectoryPass::class, $classes);
    }

    /**
     * @param list<array<string, mixed>>  $configs
     * @param array<string, class-string> $bundles
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function presets(ContainerBuilder $container): Presets
    {
        $fresh = new ContainerBuilder();
        $fresh->setDefinition(Presets::class, $container->getDefinition(Presets::class))->setPublic(true);
        $fresh->compile(true);
        $presets = $fresh->get(Presets::class);
        self::assertInstanceOf(Presets::class, $presets);

        return $presets;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilModerationBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
