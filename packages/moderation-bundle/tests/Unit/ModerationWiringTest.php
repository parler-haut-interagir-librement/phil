<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit;

use Phil\IncidentMode\Contract\ActivationStore;
use Phil\IncidentMode\Contract\EstablishedMembers;
use Phil\IncidentMode\Contract\LiftScheduler;
use Phil\IncidentMode\Dbal\DbalActivationStore;
use Phil\IncidentMode\Dbal\IncidentSchemaListener;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\IncidentRestrictions;
use Phil\IncidentMode\Messenger\LiftExpiredIncidentsHandler;
use Phil\IncidentMode\Messenger\LiftIncidentHandler;
use Phil\IncidentMode\Messenger\MessengerLiftScheduler;
use Phil\IncidentMode\NoEstablishedMembers;
use Phil\IncidentMode\NoLiftScheduler;
use Phil\IncidentMode\Presets;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Application\Contract\SandboxPolicy;
use Phil\ModerationBundle\Application\Contract\TrustedHiders;
use Phil\ModerationBundle\Application\Contract\WordScreen;
use Phil\ModerationBundle\Application\Decisions;
use Phil\ModerationBundle\Application\Event\StatementIssued;
use Phil\ModerationBundle\Application\FirstPostSandbox;
use Phil\ModerationBundle\Application\MemberNotes;
use Phil\ModerationBundle\Application\ModerationQueue;
use Phil\ModerationBundle\Application\ModerationTargets;
use Phil\ModerationBundle\Application\NoCurrentMember;
use Phil\ModerationBundle\Application\NoTrustedHiders;
use Phil\ModerationBundle\Application\NoWordScreen;
use Phil\ModerationBundle\Application\PublicJournal;
use Phil\ModerationBundle\Application\ReasonGrounds;
use Phil\ModerationBundle\Application\Reasons;
use Phil\ModerationBundle\Application\Redress;
use Phil\ModerationBundle\Application\Reports;
use Phil\ModerationBundle\Application\ReportSurge;
use Phil\ModerationBundle\Application\Roles;
use Phil\ModerationBundle\Application\StatementsOfReasons;
use Phil\ModerationBundle\Application\Submissions;
use Phil\ModerationBundle\Application\TemporaryHides;
use Phil\ModerationBundle\Domain\Contract\DecisionRepository;
use Phil\ModerationBundle\Domain\Contract\MemberNoteRepository;
use Phil\ModerationBundle\Domain\Contract\ReportRepository;
use Phil\ModerationBundle\Domain\Contract\SandboxRepository;
use Phil\ModerationBundle\Domain\Contract\StatementRepository;
use Phil\ModerationBundle\Infrastructure\DependencyInjection\ModerationServices;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalDecisionRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalMemberNoteRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalSandboxRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalStatementRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationSchemaListener;
use Phil\ModerationBundle\Infrastructure\Http\AdminController;
use Phil\ModerationBundle\Infrastructure\Http\JournalController;
use Phil\ModerationBundle\Infrastructure\Http\ReportController;
use Phil\ModerationBundle\Infrastructure\Http\StatementController;
use Phil\ModerationBundle\Infrastructure\IncidentMode\CurrentMemberRestrictions;
use Phil\ModerationBundle\Infrastructure\IncidentMode\IncidentPanel;
use Phil\ModerationBundle\Infrastructure\Notifications\StatementNotifier;
use Phil\ModerationBundle\Infrastructure\Notifications\StatementSubjects;
use Phil\ModerationBundle\Infrastructure\Privacy\ModerationPrivacy;
use Phil\ModerationBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\ModerationBundle\Infrastructure\Security\TokenCurrentModerator;
use Phil\ModerationBundle\Infrastructure\Twig\KitComponents;
use Phil\ModerationBundle\Infrastructure\Twig\ModerationExtension;
use Phil\ModerationBundle\PhilModerationBundle;
use Phil\NotificationsBundle\Application\Notifications;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Each service of the bundle receives the collaborators the constructor names, in its order.
 */
#[CoversClass(ModerationServices::class)]
#[CoversClass(PhilModerationBundle::class)]
final class ModerationWiringTest extends TestCase
{
    public function testEveryServiceIsGivenItsCollaborators(): void
    {
        $container = $this->load([], ['SecurityBundle' => self::class]);
        $connection = new Reference('doctrine.dbal.default_connection');
        $clock = new Reference('clock');
        $events = new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        $expected = [
            DecisionRepository::class => [DbalDecisionRepository::class, [$connection]],
            MemberNoteRepository::class => [DbalMemberNoteRepository::class, [$connection]],
            SandboxRepository::class => [DbalSandboxRepository::class, [$connection]],
            ModerationSchemaListener::class => [ModerationSchemaListener::class, [$connection]],
            Reports::class => [Reports::class, [new Reference(ReportRepository::class), new Reference(ModerationTargets::class), new Reference(Reasons::class), new Reference(CurrentMember::class), $clock, $events]],
            Decisions::class => [Decisions::class, [new Reference(DecisionRepository::class), new Reference(ReportRepository::class), new Reference(SandboxRepository::class), new Reference(ModerationTargets::class), new Reference(CurrentModerator::class), $clock, $events, new Reference(StatementsOfReasons::class)]],
            StatementRepository::class => [DbalStatementRepository::class, [$connection]],
            ReasonGrounds::class => [ReasonGrounds::class, [[]]],
            Redress::class => [Redress::class, [null, null, []]],
            StatementsOfReasons::class => [StatementsOfReasons::class, [new Reference(StatementRepository::class), new Reference(Reasons::class), new Reference(ReasonGrounds::class), new Reference(CurrentMember::class), new Reference(Redress::class), $events]],
            StatementController::class => [StatementController::class, [new Reference(StatementsOfReasons::class), new Reference(CurrentMember::class), new Reference('twig'), '@PhilModeration/layout.html.twig', 25]],
            IncidentRestrictions::class => [IncidentRestrictions::class, [new Reference(IncidentMode::class), new Reference(EstablishedMembers::class), []]],
            CurrentMemberRestrictions::class => [CurrentMemberRestrictions::class, [new Reference(IncidentRestrictions::class), new Reference(CurrentMember::class)]],
            ModerationQueue::class => [ModerationQueue::class, [new Reference(ReportRepository::class), new Reference(DecisionRepository::class), new Reference(ModerationTargets::class), new Reference(CurrentModerator::class)]],
            Submissions::class => [Submissions::class, [new Reference(Reports::class), new Reference(WordScreen::class), new Reference(SandboxPolicy::class), $events]],
            TemporaryHides::class => [TemporaryHides::class, [new Reference(Reports::class), new Reference(ModerationTargets::class), new Reference(TrustedHiders::class), new Reference(CurrentMember::class), $events]],
            MemberNotes::class => [MemberNotes::class, [new Reference(MemberNoteRepository::class), new Reference(CurrentModerator::class), $clock]],
            PublicJournal::class => [PublicJournal::class, [new Reference(DecisionRepository::class)]],
            TokenCurrentMember::class => [TokenCurrentMember::class, [new Reference('security.token_storage')]],
            TokenCurrentModerator::class => [TokenCurrentModerator::class, [new Reference(CurrentMember::class), new Reference('security.authorization_checker'), new Reference(Roles::class)]],
            ActivationStore::class => [DbalActivationStore::class, [$connection]],
            IncidentSchemaListener::class => [IncidentSchemaListener::class, [$connection]],
            MessengerLiftScheduler::class => [MessengerLiftScheduler::class, [new Reference('messenger.default_bus'), $clock]],
            IncidentMode::class => [IncidentMode::class, [new Reference(Presets::class), new Reference(ActivationStore::class), $clock, $events, new Reference(LiftScheduler::class)]],
            LiftIncidentHandler::class => [LiftIncidentHandler::class, [new Reference(IncidentMode::class)]],
            LiftExpiredIncidentsHandler::class => [LiftExpiredIncidentsHandler::class, [new Reference(IncidentMode::class)]],
            IncidentPanel::class => [IncidentPanel::class, [new Reference(IncidentMode::class), new Reference(ReportSurge::class)]],
            ReportController::class => [ReportController::class, [new Reference(Reports::class), new Reference(TemporaryHides::class), new Reference('security.csrf.token_manager')]],
        ];

        foreach ($expected as $id => [$class, $arguments]) {
            self::assertTrue($container->hasDefinition($id), $id);
            self::assertSame($class, $container->getDefinition($id)->getClass(), $id);
            self::assertEquals($arguments, $container->getDefinition($id)->getArguments(), $id);
        }

        self::assertSame(DbalActivationStore::class, $container->getDefinition(ActivationStore::class)->getClass());
        self::assertSame([['event' => 'postGenerateSchema']], $container->getDefinition(IncidentSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame([[]], $container->getDefinition(LiftExpiredIncidentsHandler::class)->getTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(ReportController::class)->isPublic());
        self::assertTrue($container->getDefinition(JournalController::class)->isPublic());
        self::assertTrue($container->getDefinition(AdminController::class)->isPublic());
        self::assertTrue($container->getDefinition(StatementController::class)->isPublic());
        self::assertTrue($container->getDefinition(AdminController::class)->getArgument(12));
        self::assertTrue($container->getDefinition(IncidentMode::class)->isPublic());
        self::assertSame(NoLiftScheduler::class, $container->getDefinition(NoLiftScheduler::class)->getClass());
    }

    public function testThePrivacyAndTheTwigExtensionAreGivenTheirCollaborators(): void
    {
        $container = $this->load([], ['SecurityBundle' => self::class]);

        self::assertEquals(
            [new Reference(ReportRepository::class), new Reference(DecisionRepository::class), new Reference(MemberNoteRepository::class), new Reference(SandboxRepository::class), new Reference(ActivationStore::class), new Reference(StatementRepository::class)],
            $container->getDefinition(ModerationPrivacy::class)->getArguments(),
        );
        self::assertEquals(
            [new Reference(Reasons::class), new Reference(TemporaryHides::class), new Reference('security.csrf.token_manager'), new Reference(CurrentMemberRestrictions::class), new Reference(KitComponents::class)],
            $container->getDefinition(ModerationExtension::class)->getArguments(),
        );
    }

    public function testThePortsHaveTheirDefaultServices(): void
    {
        $container = $this->load([], ['SecurityBundle' => self::class]);

        foreach ([NoWordScreen::class, NoTrustedHiders::class, NoEstablishedMembers::class, FirstPostSandbox::class] as $default) {
            self::assertTrue($container->hasDefinition($default), $default);
        }

        self::assertSame(NoEstablishedMembers::class, (string) $container->getAlias(EstablishedMembers::class));

        $withoutSecurity = $this->load([]);

        self::assertTrue($withoutSecurity->hasDefinition(NoCurrentMember::class));
        self::assertSame(NoCurrentMember::class, (string) $withoutSecurity->getAlias(CurrentMember::class));
        self::assertSame(NoCurrentMember::class, (string) $withoutSecurity->getAlias(CurrentModerator::class));
    }

    public function testTheStatementsOfReasonsAreConfigured(): void
    {
        $container = $this->load([[
            'statements' => [
                'grounds' => ['hate' => ['ground' => 'illegal', 'reference' => 'Criminal Code, s. 319']],
                'terms_url' => '/terms',
                'complaint_url' => 'https://help.example/contest',
                'out_of_court' => [['name' => 'A body', 'url' => 'https://body.example']],
                'page_size' => 200,
            ],
            'incident' => ['spare_established' => ['new-posts']],
        ]], ['SecurityBundle' => self::class]);

        self::assertEquals([['hate' => ['ground' => 'illegal', 'reference' => 'Criminal Code, s. 319']]], $container->getDefinition(ReasonGrounds::class)->getArguments());
        self::assertSame(['/terms', 'https://help.example/contest', [['name' => 'A body', 'url' => 'https://body.example']]], $container->getDefinition(Redress::class)->getArguments());
        self::assertSame(200, $container->getDefinition(StatementController::class)->getArgument(4));
        self::assertSame(['new-posts'], $container->getDefinition(IncidentRestrictions::class)->getArgument(2));
        self::assertFalse($container->hasDefinition(StatementNotifier::class));
    }

    public function testAGroundOfTheTermsAndAStatementPageOfOneAreAccepted(): void
    {
        $container = $this->load([['statements' => ['grounds' => ['spam' => ['ground' => 'terms']], 'page_size' => 1]]], ['SecurityBundle' => self::class]);

        self::assertEquals([['spam' => ['ground' => 'terms', 'reference' => null]]], $container->getDefinition(ReasonGrounds::class)->getArguments());
        self::assertSame(1, $container->getDefinition(StatementController::class)->getArgument(4));
    }

    public function testTheStatementsOfReasonsTurnedOff(): void
    {
        $container = $this->load([['statements' => false]], ['SecurityBundle' => self::class]);

        self::assertFalse($container->hasDefinition(StatementsOfReasons::class));
        self::assertFalse($container->hasDefinition(StatementController::class));
        self::assertNull($container->getDefinition(Decisions::class)->getArgument(7));
        self::assertFalse($container->getDefinition(AdminController::class)->getArgument(12));
        self::assertTrue($container->hasDefinition(StatementRepository::class));
    }

    public function testTheNotificationOfAStatement(): void
    {
        $bundles = ['SecurityBundle' => self::class, 'PhilNotificationsBundle' => self::class];
        $container = $this->load([['statements' => ['notification_type' => 'moderation.statement']]], $bundles);

        self::assertEquals([new Reference(Notifications::class), 'moderation.statement'], $container->getDefinition(StatementNotifier::class)->getArguments());
        self::assertSame([['event' => StatementIssued::class]], $container->getDefinition(StatementNotifier::class)->getTag('kernel.event_listener'));
        self::assertEquals([new Reference('translator'), new Reference('router')], $container->getDefinition(StatementSubjects::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(StatementSubjects::class)->getTag('phil_notifications.subject_resolver'));

        self::assertFalse($this->load([], $bundles)->hasDefinition(StatementNotifier::class));
        self::assertFalse($this->load([['statements' => ['notification_type' => 'moderation.statement']]], ['SecurityBundle' => self::class])->hasDefinition(StatementSubjects::class));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'a script' => ['javascript:alert(1)'];
        yield 'another host by a relative scheme' => ['//elsewhere.example/terms'];
        yield 'plain http' => ['http://help.example'];
        yield 'a relative path' => ['terms'];
        yield 'a space' => ['/terms of use'];
        yield 'a newline' => ["/terms\n"];
        yield 'a number' => [42];
    }

    #[DataProvider('refusedAddresses')]
    public function testAnAddressThatIsNeitherAPathNorHttpsIsRefused(mixed $address): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['statements' => ['terms_url' => $address]]]);
    }

    public function testTheOtherAddressesAreCheckedToo(): void
    {
        foreach ([['complaint_url' => 'javascript:x'], ['out_of_court' => [['name' => 'A body', 'url' => 'data:text/html,x']]]] as $statements) {
            try {
                $this->load([['statements' => $statements]]);
                self::fail('The address was accepted.');
            } catch (InvalidConfigurationException $invalid) {
                self::assertStringContainsString('is neither a path nor an https address.', $invalid->getMessage());
            }
        }

        self::assertFalse(PhilModerationBundle::notAnAddress(null));
        self::assertFalse(PhilModerationBundle::notAnAddress('/'));
        self::assertFalse(PhilModerationBundle::notAnAddress('https://help.example'));
    }

    public function testTheJournalIsPagedByDefaultBy50(): void
    {
        $arguments = $this->load([])->getDefinition(JournalController::class)->getArguments();

        self::assertSame(50, $arguments[3]);
    }

    public function testTheLimitsOfTheConfigurationAreInclusive(): void
    {
        $container = $this->load([[
            'admin' => ['page_size' => 1],
            'journal' => ['page_size' => 200],
            'surge' => ['reports' => 1, 'within' => 60],
        ]], ['SecurityBundle' => self::class]);

        self::assertSame(1, $container->getDefinition(AdminController::class)->getArgument(9));
        self::assertSame(200, $container->getDefinition(JournalController::class)->getArgument(3));
        self::assertSame([1, 60], \array_slice($container->getDefinition(ReportSurge::class)->getArguments(), 2));

        $largest = $this->load([['admin' => ['page_size' => 200], 'journal' => ['page_size' => 1], 'surge' => ['within' => 86_400]]], ['SecurityBundle' => self::class]);

        self::assertSame(200, $largest->getDefinition(AdminController::class)->getArgument(9));
        self::assertSame(1, $largest->getDefinition(JournalController::class)->getArgument(3));
        self::assertSame(86_400, $largest->getDefinition(ReportSurge::class)->getArgument(3));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedBounds(): iterable
    {
        yield 'an empty admin page' => [['admin' => ['page_size' => 0]]];
        yield 'a journal page too large' => [['journal' => ['page_size' => 201]]];
        yield 'an empty page of statements' => [['statements' => ['page_size' => 0]]];
        yield 'a page of statements too large' => [['statements' => ['page_size' => 201]]];
        yield 'a ground of no kind' => [['statements' => ['grounds' => ['spam' => ['ground' => 'law']]]]];
        yield 'a default of no minute' => [['incident' => ['presets' => [['name' => 'q', 'label' => 'Q', 'restrictions' => ['new-posts'], 'default_minutes' => 0, 'maximum_minutes' => 1]]]]];
        yield 'a maximum of no minute' => [['incident' => ['presets' => [['name' => 'q', 'label' => 'Q', 'restrictions' => ['new-posts'], 'default_minutes' => 1, 'maximum_minutes' => 0]]]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refusedBounds')]
    public function testTheBoundsAreRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([$config]);
    }

    public function testAPresetOfOneMinuteIsAccepted(): void
    {
        $container = $this->load([['incident' => ['presets' => [['name' => 'q', 'label' => 'Q', 'restrictions' => ['new-posts'], 'default_minutes' => 1, 'maximum_minutes' => 1]]]]]);

        self::assertTrue($container->hasDefinition(Presets::class));
    }

    /**
     * @param list<array<string, mixed>> $configs
     * @param array<string, string>      $bundles
     */
    private function load(array $configs, array $bundles = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);

        $extension = new PhilModerationBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load($configs, $container);

        return $container;
    }
}
