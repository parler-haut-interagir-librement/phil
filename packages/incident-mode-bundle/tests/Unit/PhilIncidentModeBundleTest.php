<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit;

use Phil\AuditBundle\Application\AuditTrail;
use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\Contract\EstablishedMembers;
use Phil\IncidentMode\Contract\LiftScheduler;
use Phil\IncidentMode\Dbal\DbalActivationStore;
use Phil\IncidentMode\Dbal\IncidentSchemaListener;
use Phil\IncidentMode\Event\IncidentExtended;
use Phil\IncidentMode\Event\IncidentLifted;
use Phil\IncidentMode\Event\IncidentStarted;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\IncidentRestrictions;
use Phil\IncidentMode\Messenger\LiftExpiredIncidents;
use Phil\IncidentMode\Messenger\LiftExpiredIncidentsHandler;
use Phil\IncidentMode\Messenger\LiftIncident;
use Phil\IncidentMode\Messenger\LiftIncidentHandler;
use Phil\IncidentMode\Messenger\MessengerLiftScheduler;
use Phil\IncidentMode\NoEstablishedMembers;
use Phil\IncidentMode\NoLiftScheduler;
use Phil\IncidentMode\Preset;
use Phil\IncidentMode\Presets;
use Phil\IncidentModeBundle\Application\Checkpoint;
use Phil\IncidentModeBundle\Application\Contract\CurrentMember;
use Phil\IncidentModeBundle\Infrastructure\Audit\AuditIncidents;
use Phil\IncidentModeBundle\Infrastructure\Console\ExtendIncidentCommand;
use Phil\IncidentModeBundle\Infrastructure\Console\LiftIncidentCommand;
use Phil\IncidentModeBundle\Infrastructure\Console\ListIncidentsCommand;
use Phil\IncidentModeBundle\Infrastructure\Console\StartIncidentCommand;
use Phil\IncidentModeBundle\Infrastructure\DependencyInjection\IncidentModePass;
use Phil\IncidentModeBundle\Infrastructure\DependencyInjection\IncidentModeServices;
use Phil\IncidentModeBundle\Infrastructure\Form\SuspendedDuringTypeExtension;
use Phil\IncidentModeBundle\Infrastructure\Http\AdminRequest;
use Phil\IncidentModeBundle\Infrastructure\Http\CheckpointListener;
use Phil\IncidentModeBundle\Infrastructure\Http\IncidentAdminController;
use Phil\IncidentModeBundle\Infrastructure\Http\SuspensionPage;
use Phil\IncidentModeBundle\Infrastructure\Privacy\IncidentPrivacy;
use Phil\IncidentModeBundle\Infrastructure\Scheduler\IncidentSchedule;
use Phil\IncidentModeBundle\Infrastructure\Security\SecurityCurrentMember;
use Phil\IncidentModeBundle\Infrastructure\Translation\IncidentWording;
use Phil\IncidentModeBundle\Infrastructure\Twig\IncidentModeExtension;
use Phil\IncidentModeBundle\Infrastructure\Twig\KitComponents;
use Phil\IncidentModeBundle\PhilIncidentModeBundle;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\TwigExtra;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Translation\IdentityTranslator;

#[CoversClass(PhilIncidentModeBundle::class)]
#[CoversClass(IncidentModeServices::class)]
#[CoversClass(IncidentModePass::class)]
final class PhilIncidentModeBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_incident_mode', $this->extension()->getAlias());
    }

    public function testTheServicesOfTheDefaultConfiguration(): void
    {
        $container = $this->load([]);

        self::assertSame(DbalActivationStore::class, (string) $container->getAlias(ActivationStore::class));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalActivationStore::class)->getArguments());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(IncidentSchemaListener::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(IncidentSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame(MessengerLiftScheduler::class, (string) $container->getAlias(LiftScheduler::class));
        self::assertEquals([new Reference('messenger.default_bus'), new Reference('clock')], $container->getDefinition(MessengerLiftScheduler::class)->getArguments());
        self::assertSame('messenger.default_bus', $container->getParameter(IncidentModeServices::BUS_PARAMETER));
        self::assertSame([['handles' => LiftIncident::class]], $container->getDefinition(LiftIncidentHandler::class)->getTag('messenger.message_handler'));
        self::assertSame([['handles' => LiftExpiredIncidents::class]], $container->getDefinition(LiftExpiredIncidentsHandler::class)->getTag('messenger.message_handler'));
        self::assertEquals([new Reference(IncidentMode::class)], $container->getDefinition(LiftIncidentHandler::class)->getArguments());
        self::assertEquals([new Reference(IncidentMode::class)], $container->getDefinition(LiftExpiredIncidentsHandler::class)->getArguments());
        self::assertSame([['name' => 'incident_mode']], $container->getDefinition(IncidentSchedule::class)->getTag('scheduler.schedule_provider'));
        self::assertSame(['5 minutes'], $container->getDefinition(IncidentSchedule::class)->getArguments());
        self::assertEquals([new Reference(Presets::class), new Reference(ActivationStore::class), new Reference('clock'), new Reference('event_dispatcher', 2), new Reference(LiftScheduler::class)], $container->getDefinition(IncidentMode::class)->getArguments());
        self::assertSame(NoEstablishedMembers::class, (string) $container->getAlias(EstablishedMembers::class));
        self::assertTrue($container->hasDefinition(NoEstablishedMembers::class));
        self::assertTrue($container->hasDefinition(NoLiftScheduler::class));
        self::assertSame(SecurityCurrentMember::class, (string) $container->getAlias(CurrentMember::class));
        self::assertEquals([new Reference('security.token_storage', 2)], $container->getDefinition(SecurityCurrentMember::class)->getArguments());
        self::assertEquals([new Reference(IncidentMode::class), new Reference(EstablishedMembers::class), []], $container->getDefinition(IncidentRestrictions::class)->getArguments());
        self::assertEquals([new Reference(IncidentMode::class), new Reference(IncidentRestrictions::class), new Reference(CurrentMember::class), new Reference('clock'), 503], $container->getDefinition(Checkpoint::class)->getArguments());
        self::assertEquals([new Reference('translator'), null], $container->getDefinition(IncidentWording::class)->getArguments());
        self::assertNull($container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertEquals([new Reference(Checkpoint::class), new Reference(IncidentWording::class), new Reference(KitComponents::class)], $container->getDefinition(IncidentModeExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(IncidentModeExtension::class)->hasTag('twig.runtime'));
        self::assertTrue($container->getDefinition(IncidentModeExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(IncidentModeServices::INTL_EXTENSION)->hasTag('twig.extension'));
        self::assertSame([['extended_type' => FormType::class]], $container->getDefinition(SuspendedDuringTypeExtension::class)->getTag('form.type_extension'));
        self::assertEquals([new Reference(Checkpoint::class), new Reference(IncidentWording::class)], $container->getDefinition(SuspendedDuringTypeExtension::class)->getArguments());
        self::assertEquals([new Reference(Checkpoint::class), new Reference('twig'), '@PhilIncidentMode/layout.html.twig'], $container->getDefinition(SuspensionPage::class)->getArguments());
        self::assertTrue($container->getDefinition(SuspensionPage::class)->isPublic());
        self::assertTrue($container->getDefinition(SuspensionPage::class)->hasTag('controller.service_arguments'));
        self::assertEquals([new Reference(Checkpoint::class), new Reference(SuspensionPage::class)], $container->getDefinition(CheckpointListener::class)->getArguments());
        self::assertSame([
            ['event' => 'kernel.controller', 'method' => 'onKernelController'],
            ['event' => 'kernel.exception', 'method' => 'onKernelException'],
        ], $container->getDefinition(CheckpointListener::class)->getTag('kernel.event_listener'));
        $admin = $container->getDefinition(IncidentAdminController::class);
        self::assertTrue($admin->isPublic());
        self::assertTrue($admin->hasTag('controller.service_arguments'));
        self::assertEquals([
            new Reference(IncidentMode::class), new Reference(CurrentMember::class), new Reference(AdminRequest::class), new Reference('twig'), new Reference('router'),
            new Reference('security.authorization_checker', 2), 'ROLE_ADMIN', '@PhilIncidentMode/layout.html.twig', 20,
        ], $admin->getArguments());
        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(AdminRequest::class)->getArguments());
        self::assertSame('ROLE_ADMIN', $container->getParameter(IncidentModeServices::ADMIN_ROLE_PARAMETER));
        self::assertFalse($container->hasDefinition(AuditIncidents::class));
        self::assertFalse($container->hasDefinition(IncidentPrivacy::class));

        foreach ([StartIncidentCommand::class, ExtendIncidentCommand::class, LiftIncidentCommand::class, ListIncidentsCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'));
            self::assertEquals([new Reference(IncidentMode::class)], $container->getDefinition($command)->getArguments());
        }

        self::assertSame(array_map(static fn (Preset $preset): string => $preset->name, Presets::builtIn()), array_map(static fn (Preset $preset): string => $preset->name, $this->presets([])->all()));
    }

    public function testTheConfigurationChoosesTheConnectionTheMembersAndThePages(): void
    {
        $container = $this->load([[
            'connection' => 'safety',
            'established_members' => 'app.trust_levels',
            'current_member' => 'app.member',
            'spare_established' => ['new-posts', 'new-comments'],
            'layout' => 'base.html.twig',
            'kit_subdirectory' => 'kit',
            'timezone' => 'America/Toronto',
            'checkpoints' => ['status' => 429],
            'admin' => ['role' => 'ROLE_SAFETY', 'page_size' => 50],
            'lifting' => ['bus' => 'command.bus', 'schedule' => ['name' => 'safety', 'frequency' => '1 minute']],
        ]]);

        self::assertEquals([new Reference('doctrine.dbal.safety_connection')], $container->getDefinition(DbalActivationStore::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'safety']], $container->getDefinition(IncidentSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame('app.trust_levels', (string) $container->getAlias(EstablishedMembers::class));
        self::assertSame('app.member', (string) $container->getAlias(CurrentMember::class));
        self::assertSame(['new-posts', 'new-comments'], $container->getDefinition(IncidentRestrictions::class)->getArgument(2));
        self::assertSame(429, $container->getDefinition(Checkpoint::class)->getArgument(4));
        self::assertSame('America/Toronto', $container->getDefinition(IncidentWording::class)->getArgument(1));
        self::assertSame('kit', $container->getDefinition(KitComponents::class)->getArgument(0));
        self::assertSame('base.html.twig', $container->getDefinition(SuspensionPage::class)->getArgument(2));
        self::assertSame(['ROLE_SAFETY', 'base.html.twig', 50], \array_slice($container->getDefinition(IncidentAdminController::class)->getArguments(), 6));
        self::assertSame('ROLE_SAFETY', $container->getParameter(IncidentModeServices::ADMIN_ROLE_PARAMETER));
        self::assertEquals(new Reference('command.bus'), $container->getDefinition(MessengerLiftScheduler::class)->getArgument(0));
        self::assertSame('command.bus', $container->getParameter(IncidentModeServices::BUS_PARAMETER));
        self::assertSame([['name' => 'safety']], $container->getDefinition(IncidentSchedule::class)->getTag('scheduler.schedule_provider'));
        self::assertSame(['1 minute'], $container->getDefinition(IncidentSchedule::class)->getArguments());
    }

    public function testThePresetsAreAddedReplacedAndLeftOut(): void
    {
        $presets = $this->presets([
            'without_presets' => ['suspend-new-messages'],
            'presets' => [
                'suspend-signups' => ['label' => 'No new accounts', 'restrictions' => ['signups'], 'proposed' => '1h', 'longest' => '1d'],
                'suspend-listings' => ['label' => 'Suspend new listings', 'restrictions' => ['new-listings', 'uploads'], 'proposed' => '4h', 'longest' => '2d'],
            ],
        ]);

        self::assertSame(['suspend-signups', 'suspend-invitations', 'suspend-contributions', 'read-only', 'suspend-listings'], array_map(static fn (Preset $preset): string => $preset->name, $presets->all()));
        self::assertSame('No new accounts', $presets->get('suspend-signups')->label);
        self::assertSame(3_600, $presets->get('suspend-signups')->defaultDuration->seconds);
        self::assertSame(86_400, $presets->get('suspend-signups')->maximumDuration->seconds);
        $listings = $presets->get('suspend-listings');
        self::assertSame('Suspend new listings', $listings->label);
        self::assertSame(['new-listings', 'uploads'], $listings->restrictionNames());
        self::assertSame(14_400, $listings->defaultDuration->seconds);
        self::assertSame(172_800, $listings->maximumDuration->seconds);
    }

    public function testThePresetsOfTheConfigurationAloneWithoutTheBuiltInOnes(): void
    {
        $presets = $this->presets([
            'built_in_presets' => false,
            'presets' => ['suspend-listings' => ['label' => 'Suspend new listings', 'restrictions' => ['new-listings'], 'proposed' => '4h', 'longest' => '2d']],
        ]);

        self::assertSame(['suspend-listings'], array_map(static fn (Preset $preset): string => $preset->name, $presets->all()));
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function statuses(): iterable
    {
        foreach ([503, 429, 423, 403] as $status) {
            yield 'accepted '.$status => [$status, true];
        }

        foreach ([402, 404, 422, 424, 502, 504] as $status) {
            yield 'refused '.$status => [$status, false];
        }
    }

    #[DataProvider('statuses')]
    public function testTheStatusOfARefusalIsOneOfTheFour(int $status, bool $accepted): void
    {
        if (!$accepted) {
            $this->expectException(InvalidConfigurationException::class);
        }

        $container = $this->load([['checkpoints' => ['status' => $status]]]);
        self::assertSame($status, $container->getDefinition(Checkpoint::class)->getArgument(4));
    }

    public function testAnIntegerDurationIsMinutes(): void
    {
        self::assertSame(5_400, $this->presets(['presets' => ['listings' => ['label' => 'Listings', 'restrictions' => ['new-listings'], 'proposed' => 90, 'longest' => '2h']]])->get('listings')->defaultDuration->seconds);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedConfigurations(): iterable
    {
        yield 'a preset name' => [['presets' => ['Suspend_Listings' => ['label' => 'Listings', 'restrictions' => ['new-listings'], 'proposed' => '1h', 'longest' => '2h']]], 'The preset "Suspend_Listings" of phil_incident_mode.presets is refused: "Suspend_Listings" is not a preset name'];
        yield 'a restriction name' => [['presets' => ['listings' => ['label' => 'Listings', 'restrictions' => ['New Listings'], 'proposed' => '1h', 'longest' => '2h']]], 'The preset "listings" of phil_incident_mode.presets is refused'];
        yield 'a proposed duration over the longest' => [['presets' => ['listings' => ['label' => 'Listings', 'restrictions' => ['new-listings'], 'proposed' => '3h', 'longest' => '2h']]], 'proposes a duration longer than the longest'];
        yield 'a proposed duration' => [['presets' => ['listings' => ['label' => 'Listings', 'restrictions' => ['new-listings'], 'proposed' => 'soon', 'longest' => '2h']]], 'is not a duration such as 90m, 2h or 1d'];
        yield 'a longest duration' => [['presets' => ['listings' => ['label' => 'Listings', 'restrictions' => ['new-listings'], 'proposed' => '1h', 'longest' => '91d']]], 'is not a duration such as 90m, 2h or 1d'];
        yield 'a duration that is no text' => [['presets' => ['listings' => ['label' => 'Listings', 'restrictions' => ['new-listings'], 'proposed' => ['1h'], 'longest' => '2h']]], 'phil_incident_mode.presets.listings.proposed'];
        yield 'no restriction' => [['presets' => ['listings' => ['label' => 'Listings', 'restrictions' => [], 'proposed' => '1h', 'longest' => '2h']]], 'should have at least 1 element'];
        yield 'a restriction spared' => [['spare_established' => ['New Posts']], 'phil_incident_mode.spare_established names restrictions: "New Posts" is not a restriction name'];
        yield 'a time zone' => [['timezone' => 'Mars/Olympus'], 'phil_incident_mode.timezone'];
        yield 'a duration that is a float' => [['presets' => ['listings' => ['label' => 'Listings', 'restrictions' => ['new-listings'], 'proposed' => 1.5, 'longest' => '2h']]], 'is not a duration such as 90m, 2h or 1d'];
        yield 'a status' => [['checkpoints' => ['status' => 500]], 'The value 500 is not allowed'];
        yield 'a page size' => [['admin' => ['page_size' => 0]], 'Should be greater than or equal to 1'];
        yield 'a page size too large' => [['admin' => ['page_size' => 201]], 'Should be less than or equal to 200'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refusedConfigurations')]
    public function testAConfigurationTheLibraryWouldRefuseFailsTheBuild(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);
        $this->load([$config]);
    }

    public function testTheLiftingByMessengerAndTheScheduleCanBeTurnedOff(): void
    {
        $container = $this->load([['lifting' => ['messenger' => false, 'schedule' => ['enabled' => false]]]]);

        self::assertSame(NoLiftScheduler::class, (string) $container->getAlias(LiftScheduler::class));
        self::assertFalse($container->hasDefinition(MessengerLiftScheduler::class));
        self::assertFalse($container->hasDefinition(IncidentSchedule::class));
        self::assertTrue($container->hasDefinition(LiftIncidentHandler::class));
    }

    public function testTheAdministrationCanBeTurnedOff(): void
    {
        $container = $this->load([['admin' => false]]);

        self::assertFalse($container->hasDefinition(IncidentAdminController::class));
        self::assertFalse($container->hasDefinition(AdminRequest::class));
        self::assertFalse($container->hasParameter(IncidentModeServices::ADMIN_ROLE_PARAMETER));
        self::assertTrue($container->hasDefinition(SuspensionPage::class));
    }

    public function testTheAuditNeedsItsBundle(): void
    {
        self::assertFalse($this->load([], ['PhilIncidentModeBundle'])->hasDefinition(AuditIncidents::class));
        self::assertFalse($this->load([['audit' => false]], ['PhilAuditBundle'])->hasDefinition(AuditIncidents::class));

        $audit = $this->load([['audit' => ['segment' => 'safety']]], ['PhilAuditBundle'])->getDefinition(AuditIncidents::class);
        self::assertEquals([new Reference(AuditTrail::class), 'safety'], $audit->getArguments());
        self::assertSame([
            ['event' => IncidentStarted::class, 'method' => 'started'],
            ['event' => IncidentExtended::class, 'method' => 'extended'],
            ['event' => IncidentLifted::class, 'method' => 'lifted'],
        ], $audit->getTag('kernel.event_listener'));
        self::assertSame('incidents', $this->load([], ['PhilAuditBundle'])->getDefinition(AuditIncidents::class)->getArgument(1));
    }

    public function testThePrivacyNeedsItsBundle(): void
    {
        self::assertFalse($this->load([], ['PhilAuditBundle'])->hasDefinition(IncidentPrivacy::class));
        self::assertFalse($this->load([['privacy' => false]], ['PhilPrivacyBundle'])->hasDefinition(IncidentPrivacy::class));

        $privacy = $this->load([], ['PhilPrivacyBundle'])->getDefinition(IncidentPrivacy::class);
        self::assertEquals([new Reference(ActivationStore::class)], $privacy->getArguments());
        self::assertTrue($privacy->hasTag('phil_privacy.exporter'));
        self::assertTrue($privacy->hasTag('phil_privacy.eraser'));
    }

    public function testTheBundlesAreReadFromTheKernel(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', 'none');
        $this->extension()->load([], $container);
        self::assertFalse($container->hasDefinition(IncidentPrivacy::class));

        $container = new ContainerBuilder();
        $this->extension()->load([], $container);
        self::assertFalse($container->hasDefinition(IncidentPrivacy::class));
    }

    public function testTwigExtraBundleBringsTheFiltersOfTheDates(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new TwigExtra());
        $this->extension()->load([], $container);

        self::assertFalse($container->hasDefinition(IncidentModeServices::INTL_EXTENSION));
    }

    public function testTheTokenOfTheAdministrationIsStateless(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => [AdminRequest::TOKEN_ID]]]], $container->getExtensionConfig('framework'));

        $alone = new ContainerBuilder();
        $extension->prepend($alone);
        self::assertSame([], $alone->getExtensionConfig('framework'));
    }

    public function testThePassIsAdded(): void
    {
        $container = new ContainerBuilder();
        new PhilIncidentModeBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof IncidentModePass));
    }

    public function testWithoutTheBusTheSweepOfTheCommandLiftsAlone(): void
    {
        $container = $this->load([]);
        new IncidentModePass()->process($container);

        self::assertSame(NoLiftScheduler::class, (string) $container->getAlias(LiftScheduler::class));

        foreach ([MessengerLiftScheduler::class, LiftIncidentHandler::class, LiftExpiredIncidentsHandler::class, IncidentSchedule::class] as $service) {
            self::assertFalse($container->hasDefinition($service), $service);
        }
    }

    public function testWithTheBusTheDelayedMessageStays(): void
    {
        $container = $this->load([]);
        $container->setDefinition('messenger.default_bus', new Definition(stdClass::class));
        $container->setDefinition('translator', new Definition(stdClass::class));
        new IncidentModePass()->process($container);

        self::assertSame(MessengerLiftScheduler::class, (string) $container->getAlias(LiftScheduler::class));
        self::assertTrue($container->hasDefinition(MessengerLiftScheduler::class));
        self::assertTrue($container->hasDefinition(IncidentSchedule::class));
        self::assertTrue($container->hasDefinition(LiftIncidentHandler::class));
        self::assertTrue($container->hasDefinition(LiftExpiredIncidentsHandler::class));
        self::assertEquals(new Reference('translator'), $container->getDefinition(IncidentWording::class)->getArgument(0));
    }

    public function testWithoutATranslatorTheKeysAreTheWords(): void
    {
        $container = $this->load([]);
        new IncidentModePass()->process($container);

        self::assertEquals(new Definition(IdentityTranslator::class), $container->getDefinition(IncidentWording::class)->getArgument(0));
    }

    public function testTheRecipesFollowTheToolkitUnlessTheConfigurationNamesTheirPlace(): void
    {
        $container = $this->load([]);
        new IncidentModePass()->process($container);
        self::assertSame('', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new IncidentModePass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container = $this->load([['kit_subdirectory' => 'kit']]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new IncidentModePass()->process($container);
        self::assertSame('kit', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', null);
        new IncidentModePass()->process($container);
        self::assertSame('', $container->getDefinition(KitComponents::class)->getArgument(0));
    }

    public function testThePassLeavesAContainerWithoutTheBundleAlone(): void
    {
        $container = new ContainerBuilder();
        new IncidentModePass()->process($container);

        self::assertFalse($container->has(LiftScheduler::class));
        self::assertFalse($container->hasDefinition(IncidentWording::class));
        self::assertFalse($container->hasDefinition(KitComponents::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function presets(array $config): Presets
    {
        $presets = $this->load([$config])->get(Presets::class);
        self::assertInstanceOf(Presets::class, $presets);

        return $presets;
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param list<string>               $bundles
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', array_fill_keys($bundles, stdClass::class));
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilIncidentModeBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    public function testTheHandlersAreRemovedBeforeMessengerWiresThem(): void
    {
        $container = new ContainerBuilder();
        new MessengerBundle()->build($container);
        new PhilIncidentModeBundle()->build($container);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $messenger = array_search(MessengerPass::class, $passes, true);
        self::assertIsInt($messenger);

        $removal = array_search(IncidentModePass::class, $passes, true);
        self::assertIsInt($removal);
        self::assertLessThan($messenger, $removal);
    }

    public function testThePassesRunAtPriorityOneNotHigher(): void
    {
        $container = new ContainerBuilder();
        new PhilIncidentModeBundle()->build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, 2);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $marker = array_search(true, array_map(static fn (string $class): bool => str_contains($class, '@anonymous'), $passes), true);
        self::assertIsInt($marker);

        $removal = array_search(IncidentModePass::class, $passes, true);
        self::assertIsInt($removal);
        self::assertGreaterThan($marker, $removal, 'IncidentModePass must run at priority 1, after a pass of priority 2.');
    }
}
