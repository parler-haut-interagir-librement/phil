<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit;

use Phil\Shifts\Allocation\FairAllocator;
use Phil\Shifts\Allocation\InterestAllocator;
use Phil\Shifts\Roster\Conflicts;
use Phil\ShiftsBundle\Application\Absences;
use Phil\ShiftsBundle\Application\Contract\CurrentPerson;
use Phil\ShiftsBundle\Application\Contract\Transaction;
use Phil\ShiftsBundle\Application\Message\AllocateInterest;
use Phil\ShiftsBundle\Application\Minors;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Application\SwapBoard;
use Phil\ShiftsBundle\Domain\Repository\AbsenceRepository;
use Phil\ShiftsBundle\Domain\Repository\GuardianRepository;
use Phil\ShiftsBundle\Domain\Repository\RosterRepository;
use Phil\ShiftsBundle\Domain\Repository\SwapRepository;
use Phil\ShiftsBundle\Infrastructure\Console\ImportPlanCommand;
use Phil\ShiftsBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\ShiftsBundle\Infrastructure\DependencyInjection\ShiftsServices;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalAbsenceRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalGuardianRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalRosterRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalSwapRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalTransaction;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsSchemaListener;
use Phil\ShiftsBundle\Infrastructure\Form\ShiftType;
use Phil\ShiftsBundle\Infrastructure\Http\Access;
use Phil\ShiftsBundle\Infrastructure\Http\CoordinatorController;
use Phil\ShiftsBundle\Infrastructure\Http\Pages;
use Phil\ShiftsBundle\Infrastructure\Http\ShiftsGuard;
use Phil\ShiftsBundle\Infrastructure\Http\SwapController;
use Phil\ShiftsBundle\Infrastructure\Http\VolunteerController;
use Phil\ShiftsBundle\Infrastructure\Messenger\AllocateInterestHandler;
use Phil\ShiftsBundle\Infrastructure\Privacy\ShiftsPrivacy;
use Phil\ShiftsBundle\Infrastructure\Security\ShiftsVoter;
use Phil\ShiftsBundle\Infrastructure\Security\TokenCurrentPerson;
use Phil\ShiftsBundle\Infrastructure\Twig\Components\MyShiftsComponent;
use Phil\ShiftsBundle\Infrastructure\Twig\Components\ShiftCardComponent;
use Phil\ShiftsBundle\PhilShiftsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilShiftsBundle::class)]
#[CoversClass(ShiftsServices::class)]
#[CoversClass(CsrfProtectionPass::class)]
final class PhilShiftsBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_shifts', $this->extension()->getAlias());
        self::assertSame([0], $container->getDefinition(Conflicts::class)->getArguments());
        self::assertSame(FairAllocator::class, (string) $container->getAlias(InterestAllocator::class));
        self::assertEquals(new Reference('doctrine.dbal.default_connection'), $container->getDefinition(DbalRosterRepository::class)->getArgument(0));
        self::assertSame([null, 'ROLE_SHIFTS_COORDINATOR'], \array_slice($container->getDefinition(ShiftsVoter::class)->getArguments(), 1));
        self::assertTrue($container->getDefinition(ShiftsVoter::class)->hasTag('security.voter'));
        self::assertTrue($container->getDefinition(Rosters::class)->isPublic());
        self::assertTrue($container->getDefinition(SwapBoard::class)->isPublic());
        self::assertSame(['UTC'], $container->getDefinition(ShiftType::class)->getArguments());
        self::assertTrue($container->getDefinition(ShiftType::class)->hasTag('form.type'));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(ShiftsSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame([['handles' => AllocateInterest::class]], $container->getDefinition(AllocateInterestHandler::class)->getTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(ImportPlanCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(ShiftsPrivacy::class)->hasTag('phil_privacy.exporter'));
        self::assertTrue($container->getDefinition(ShiftsPrivacy::class)->hasTag('phil_privacy.eraser'));
        // Without TwigBundle, no page and no component.
        self::assertFalse($container->hasDefinition(ShiftsGuard::class));
        self::assertFalse($container->hasDefinition(ShiftCardComponent::class));
    }

    public function testTheConfiguredValues(): void
    {
        $container = $this->load([[
            'connection' => 'shifts',
            'allocator' => 'app.allocator',
            'person' => 'app.person',
            'time_zone' => 'America/Toronto',
            'rest_minutes' => 30,
            'roles' => ['volunteer' => 'ROLE_VOLUNTEER', 'coordinator' => 'ROLE_COORDINATOR'],
            'minors' => ['birth_dates' => 'app.birth_dates', 'age_of_majority' => 16],
            'absences' => ['threshold' => 3, 'window_days' => 180],
        ]], ['TwigBundle' => 'TwigBundle']);

        self::assertEquals([new Reference(GuardianRepository::class), new Reference('clock'), new Reference('app.birth_dates'), 16, 'America/Toronto'], $container->getDefinition(Minors::class)->getArguments());
        self::assertEquals([new Reference(AbsenceRepository::class), new Reference(RosterRepository::class), new Reference('clock'), 3, 180], $container->getDefinition(Absences::class)->getArguments());
        self::assertEquals(new Reference('doctrine.dbal.shifts_connection'), $container->getDefinition(DbalAbsenceRepository::class)->getArgument(0));
        self::assertEquals(new Reference('doctrine.dbal.shifts_connection'), $container->getDefinition(DbalGuardianRepository::class)->getArgument(0));

        self::assertSame([30], $container->getDefinition(Conflicts::class)->getArguments());
        self::assertSame('app.allocator', (string) $container->getAlias(InterestAllocator::class));
        self::assertSame('app.person', (string) $container->getAlias(CurrentPerson::class));
        self::assertFalse($container->hasDefinition(TokenCurrentPerson::class));
        self::assertEquals(new Reference('doctrine.dbal.shifts_connection'), $container->getDefinition(DbalRosterRepository::class)->getArgument(0));
        self::assertSame(['ROLE_VOLUNTEER', 'ROLE_COORDINATOR'], \array_slice($container->getDefinition(ShiftsVoter::class)->getArguments(), 1));
        self::assertSame(['America/Toronto'], $container->getDefinition(ShiftType::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'shifts']], $container->getDefinition(ShiftsSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertTrue($container->getDefinition(CoordinatorController::class)->isPublic());
        self::assertEquals(new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(CoordinatorController::class)->getArgument(5));
        $card = $container->getDefinition(ShiftCardComponent::class);
        self::assertFalse($card->isShared());
        self::assertSame('America/Toronto', $card->getArgument(3));
        self::assertSame([['key' => 'PhilShifts:ShiftCard', 'template' => '@PhilShifts/components/ShiftCard.html.twig', 'expose_public_props' => false]], $card->getTag('twig.component'));
        self::assertSame([['key' => 'PhilShifts:MyShifts', 'template' => '@PhilShifts/components/MyShifts.html.twig', 'expose_public_props' => false]], $container->getDefinition(MyShiftsComponent::class)->getTag('twig.component'));
        self::assertFalse($container->getDefinition(MyShiftsComponent::class)->isShared());
    }

    public function testEveryServiceIsWiredWithItsOwnCollaborators(): void
    {
        $container = $this->load([], ['TwigBundle' => 'TwigBundle']);
        $ref = static fn (string $id, int $behavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): Reference => new Reference($id, $behavior);
        $dbal = $ref('doctrine.dbal.default_connection');
        $clock = $ref('clock');
        $events = $ref('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $shared = [$ref(Access::class), $ref(Pages::class)];

        $arguments = [
            DbalRosterRepository::class => [$dbal, $clock],
            DbalSwapRepository::class => [$dbal],
            DbalTransaction::class => [$dbal],
            DbalGuardianRepository::class => [$dbal],
            DbalAbsenceRepository::class => [$dbal],
            Minors::class => [$ref(GuardianRepository::class), $clock, null, 18, 'UTC'],
            Absences::class => [$ref(AbsenceRepository::class), $ref(RosterRepository::class), $clock, null, 365],
            ShiftsSchemaListener::class => [$dbal],
            Conflicts::class => [0],
            FairAllocator::class => [],
            Rosters::class => [$ref(RosterRepository::class), $clock, $ref(Conflicts::class), $ref(InterestAllocator::class), $events, $ref(Minors::class), $ref(Absences::class)],
            SwapBoard::class => [$ref(SwapRepository::class), $ref(RosterRepository::class), $ref(Rosters::class), $ref(Transaction::class), $clock, $ref(Conflicts::class), $events],
            ShiftsVoter::class => [$ref('security.access.decision_manager'), null, 'ROLE_SHIFTS_COORDINATOR'],
            TokenCurrentPerson::class => [$ref('security.token_storage')],
            ShiftsGuard::class => [$ref(CsrfProtectionPass::TOKEN_MANAGER)],
            Access::class => [$ref(CsrfProtectionPass::AUTHORIZATION), $ref(CurrentPerson::class)],
            Pages::class => [$ref('twig'), $ref('router'), $ref(ShiftsGuard::class), 'UTC'],
            VolunteerController::class => [$ref(Rosters::class), ...$shared],
            SwapController::class => [$ref(SwapBoard::class), ...$shared],
            CoordinatorController::class => [$ref(Rosters::class), $ref(SwapBoard::class), $ref(CsrfProtectionPass::FORM_FACTORY), ...$shared, $ref('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE), $ref(Absences::class)],
            ShiftType::class => ['UTC'],
            ShiftCardComponent::class => [$ref(Rosters::class), $ref(CurrentPerson::class), $ref(ShiftsGuard::class), 'UTC'],
            MyShiftsComponent::class => [$ref(Rosters::class), $ref(CurrentPerson::class), $clock, 'UTC'],
            AllocateInterestHandler::class => [$ref(Rosters::class), $ref('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)],
            ImportPlanCommand::class => [$ref(Rosters::class)],
            ShiftsPrivacy::class => [$ref(RosterRepository::class), $ref(SwapRepository::class), $ref(GuardianRepository::class), $ref(AbsenceRepository::class)],
        ];

        foreach ($arguments as $class => $expected) {
            self::assertTrue($container->hasDefinition($class), $class);
            self::assertEquals($expected, $container->getDefinition($class)->getArguments(), $class);
            self::assertSame($class, $container->getDefinition($class)->getClass(), $class);
        }

        $public = [Rosters::class, SwapBoard::class, Minors::class, Absences::class, VolunteerController::class, SwapController::class, CoordinatorController::class];

        foreach (array_keys($arguments) as $class) {
            self::assertSame(\in_array($class, $public, true), $container->getDefinition($class)->isPublic(), $class);
        }

        foreach ([RosterRepository::class => DbalRosterRepository::class, SwapRepository::class => DbalSwapRepository::class, GuardianRepository::class => DbalGuardianRepository::class, AbsenceRepository::class => DbalAbsenceRepository::class, Transaction::class => DbalTransaction::class, CurrentPerson::class => TokenCurrentPerson::class] as $alias => $target) {
            self::assertSame($target, (string) $container->getAlias($alias), $alias);
            self::assertSame(\in_array($alias, [RosterRepository::class, SwapRepository::class, GuardianRepository::class, AbsenceRepository::class], true), $container->getAlias($alias)->isPublic(), $alias);
        }

        self::assertTrue($container->getDefinition(ShiftsGuard::class)->isShared());
        self::assertTrue($container->getDefinition(ShiftsVoter::class)->hasTag('security.voter'));
        self::assertSame(['console.command'], array_keys($container->getDefinition(ImportPlanCommand::class)->getTags()));
        self::assertSame(['form.type'], array_keys($container->getDefinition(ShiftType::class)->getTags()));
        self::assertSame(['phil_privacy.exporter', 'phil_privacy.eraser'], array_keys($container->getDefinition(ShiftsPrivacy::class)->getTags()));
        self::assertSame(['doctrine.event_listener'], array_keys($container->getDefinition(ShiftsSchemaListener::class)->getTags()));
        self::assertSame(['messenger.message_handler'], array_keys($container->getDefinition(AllocateInterestHandler::class)->getTags()));
        self::assertTrue($container->getDefinition(ShiftCardComponent::class)->hasTag('twig.component'));
    }

    public function testThePagesOfTheTwigExtensionKnowThePersonOfSecurity(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return 'twig';
            }
        });
        $this->extension()->load([], $container);

        self::assertSame(TokenCurrentPerson::class, (string) $container->getAlias(CurrentPerson::class));
        self::assertTrue($container->hasDefinition(ShiftsGuard::class));
        self::assertFalse($this->load([['pages' => false]], ['TwigBundle' => 'TwigBundle'])->hasDefinition(ShiftsGuard::class));
        self::assertFalse($this->load([], ['Other' => 'Other'])->hasDefinition(ShiftsGuard::class));
    }

    public function testTheTimeZoneAndTheRestAreBounded(): void
    {
        foreach ([
            ['time_zone' => 'Mars/Olympus'], ['time_zone' => 'utc'], ['rest_minutes' => 1441], ['rest_minutes' => -1], ['roles' => ['coordinator' => '']],
            ['minors' => ['age_of_majority' => 0]], ['minors' => ['age_of_majority' => 31]],
            ['absences' => ['threshold' => 0]], ['absences' => ['threshold' => 101]], ['absences' => ['window_days' => 0]], ['absences' => ['window_days' => 3651]],
        ] as $config) {
            try {
                $this->load([$config]);
                self::fail('The configuration is refused.');
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame(['UTC'], $this->load([['time_zone' => 'UTC']])->getDefinition(ShiftType::class)->getArguments());
        self::assertSame([1440], $this->load([['rest_minutes' => 1440]])->getDefinition(Conflicts::class)->getArguments());
        self::assertSame([0], $this->load([['rest_minutes' => 0]])->getDefinition(Conflicts::class)->getArguments());
        self::assertSame([1, 1], \array_slice($this->load([['absences' => ['threshold' => 1, 'window_days' => 1]]])->getDefinition(Absences::class)->getArguments(), 3));
        self::assertSame([100, 3650], \array_slice($this->load([['absences' => ['threshold' => 100, 'window_days' => 3650]]])->getDefinition(Absences::class)->getArguments(), 3));
        self::assertSame(1, $this->load([['minors' => ['age_of_majority' => 1]]])->getDefinition(Minors::class)->getArgument(3));
        self::assertSame(30, $this->load([['minors' => ['age_of_majority' => 30]]])->getDefinition(Minors::class)->getArgument(3));
    }

    public function testThePagesNeedCsrfSecurityAndForms(): void
    {
        $pass = new CsrfProtectionPass();
        $container = new ContainerBuilder();
        $pass->process($container);
        self::addToAssertionCount(1);

        $container->setDefinition(ShiftsGuard::class, new Definition(ShiftsGuard::class));
        $this->assertPassRefuses($container, '"framework.csrf_protection" is false');
        $container->setDefinition(CsrfProtectionPass::TOKEN_MANAGER, new Definition(stdClass::class));
        $this->assertPassRefuses($container, 'install symfony/security-bundle');
        $container->setDefinition(CsrfProtectionPass::AUTHORIZATION, new Definition(stdClass::class));
        $this->assertPassRefuses($container, 'install symfony/form');
        $container->setDefinition(CsrfProtectionPass::FORM_FACTORY, new Definition(stdClass::class));
        $pass->process($container);

        $built = new ContainerBuilder();
        new PhilShiftsBundle()->build($built);
        self::assertContainsOnlyInstancesOf(CompilerPassInterface::class, $built->getCompilerPassConfig()->getBeforeOptimizationPasses());
        self::assertCount(1, array_filter($built->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof CsrfProtectionPass));
        self::assertInstanceOf(Reference::class, $this->load([])->getDefinition(Rosters::class)->getArgument(4));
    }

    private function assertPassRefuses(ContainerBuilder $container, string $message): void
    {
        try {
            new CsrfProtectionPass()->process($container);
            self::fail('The container is refused.');
        } catch (LogicException $logicException) {
            self::assertStringContainsString($message, $logicException->getMessage());
        }
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param array<string, string>      $bundles
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilShiftsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
