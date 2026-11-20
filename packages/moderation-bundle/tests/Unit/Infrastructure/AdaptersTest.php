<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\Presets;
use Phil\ModerationBundle\Application\Contract\ModerationMember;
use Phil\ModerationBundle\Application\Reasons;
use Phil\ModerationBundle\Application\Role;
use Phil\ModerationBundle\Application\Roles;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationSchemaListener;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use Phil\ModerationBundle\Infrastructure\EasyAdmin\ModerationAdmin;
use Phil\ModerationBundle\Infrastructure\Http\AdminController;
use Phil\ModerationBundle\Infrastructure\IncidentMode\IncidentProposal;
use Phil\ModerationBundle\Infrastructure\Privacy\ModerationPrivacy;
use Phil\ModerationBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\ModerationBundle\Infrastructure\Security\TokenCurrentModerator;
use Phil\ModerationBundle\Infrastructure\Twig\KitComponents;
use Phil\ModerationBundle\Infrastructure\Twig\ModerationExtension;
use Phil\ModerationBundle\Tests\Fixture\Stage;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

#[CoversClass(TokenCurrentMember::class)]
#[CoversClass(TokenCurrentModerator::class)]
#[CoversClass(ModerationPrivacy::class)]
#[CoversClass(IncidentProposal::class)]
#[CoversClass(ModerationExtension::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(ModerationSchemaListener::class)]
#[CoversClass(ModerationAdmin::class)]
final class AdaptersTest extends TestCase
{
    public function testTheMemberOfTheTokenIsTheirStableIdentifierOnly(): void
    {
        $tokens = new TokenStorage();
        $member = new TokenCurrentMember($tokens);

        self::assertNull($member->member());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('someone@example.org', null), 'main'));
        self::assertNull($member->member());

        $tokens->setToken(new UsernamePasswordToken($this->user('m-1'), 'main'));
        self::assertSame('m-1', $member->member()?->value);

        $tokens->setToken(new UsernamePasswordToken($this->user('someone@example.org'), 'main'));
        self::assertNull($member->member());
    }

    public function testTheModeratorActsUnderTheFirstRoleTheyHold(): void
    {
        $tokens = new TokenStorage();
        $granted = [];
        $authorization = new class($granted) implements AuthorizationCheckerInterface {
            /**
             * @param list<string> $granted
             */
            public function __construct(public array $granted)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return \in_array($attribute, $this->granted, true);
            }
        };
        $roles = new Roles([new Role('moderator', 'ROLE_MODERATOR'), new Role('administrator', 'ROLE_ADMIN')]);
        $moderator = new TokenCurrentModerator(new TokenCurrentMember($tokens), $authorization, $roles);

        $authorization->granted = ['ROLE_ADMIN', 'ROLE_MODERATOR'];
        self::assertNull($moderator->moderator());

        $tokens->setToken(new UsernamePasswordToken($this->user('m-1'), 'main'));
        self::assertSame('moderator', $moderator->moderator()?->role->name);
        self::assertSame('m-1', $moderator->moderator()?->member->value);

        $authorization->granted = ['ROLE_ADMIN'];
        self::assertSame('administrator', $moderator->moderator()?->role->name);

        $authorization->granted = [];
        self::assertNull($moderator->moderator());
    }

    public function testThePrivacyOfAMember(): void
    {
        $stage = new Stage();
        $privacy = new ModerationPrivacy($stage->reportRepository, $stage->decisionRepository, $stage->noteRepository, $stage->sandboxRepository);
        $stage->posts->contents['1'] = new TargetView('A post', author: MemberId::of('m-1'));
        $stage->people->signIn('m-1');
        $stage->reports->report(TargetReference::of('comment', '9'), 'spam', 'Ads');
        $stage->people->signIn('mod-1', $stage->moderator);
        $stage->decisions->decide(TargetReference::of('forum.post', '1'), DecisionAction::Hide, 'Spam', 'Private');
        $stage->notes->write(MemberId::of('m-1'), 'Watch this one');
        $stage->sandboxRepository->clear(MemberId::of('m-1'), $stage->clock->now());

        self::assertSame('moderation', $privacy->module()->value);
        $export = $privacy->export(DataSubject::fromString('m-1'));

        self::assertSame([[
            'target_type' => 'comment', 'target_id' => '9', 'reason' => 'spam', 'comment' => 'Ads',
            'reported_at' => '2026-11-13T10:00:00+00:00', 'closed' => false,
        ]], $export['reports']);
        self::assertSame([[
            'target_type' => 'forum.post', 'target_id' => '1', 'action' => 'hide', 'role' => 'moderator', 'reason' => 'Spam',
            'decided_at' => '2026-11-13T10:00:00+00:00',
        ]], $export['decisions_about_my_content']);
        self::assertSame([['note' => 'Watch this one', 'written_at' => '2026-11-13T10:00:00+00:00']], $export['notes_about_me']);
        self::assertSame('2026-11-13T10:00:00+00:00', $export['sandbox_left_at']);
        self::assertArrayNotHasKey('incidents', $export, 'the incidents are answered by phil/incident-mode-bundle');
        self::assertArrayNotHasKey('decisions_taken', $export);
        self::assertStringNotContainsString('mod-1', json_encode($export, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('Private', json_encode($export, \JSON_THROW_ON_ERROR));

        $moderatorExport = $privacy->export(DataSubject::fromString('mod-1'));
        self::assertSame([[
            'target_type' => 'forum.post', 'target_id' => '1', 'action' => 'hide', 'role' => 'moderator',
            'decided_at' => '2026-11-13T10:00:00+00:00',
        ]], $moderatorExport['decisions_taken']);
        self::assertSame([], $privacy->export(DataSubject::fromString('nobody')));
        self::assertSame([], $privacy->export(DataSubject::fromString('someone@example.org')));

        $report = $privacy->erase(DataSubject::fromString('m-1'));

        self::assertSame([
            ModerationTables::REPORTS => ErasureEffect::Pseudonymised,
            ModerationTables::DECISIONS => ErasureEffect::Pseudonymised,
            ModerationTables::NOTES => ErasureEffect::Deleted,
            ModerationTables::SANDBOX => ErasureEffect::Deleted,
        ], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('m-1')));
        self::assertSame($report->effects, $privacy->erase(DataSubject::fromString('m-1'))->effects);
        self::assertArrayHasKey(ModerationTables::NOTES, $privacy->erase(DataSubject::fromString('someone@example.org'))->effects);
    }

    public function testTheIncidentModeIsProposedWhenReportsSurgeToItsRoleOnly(): void
    {
        $stage = new Stage();
        $mode = new IncidentMode(Presets::defaults(), new InMemoryActivationStore(), $stage->clock);
        $authorization = new class implements AuthorizationCheckerInterface {
            /** @var list<mixed> */
            public array $asked = [];

            public bool $granted = true;

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                $this->asked[] = $attribute;

                return $this->granted;
            }
        };
        $proposal = new IncidentProposal($mode, $stage->surge, $authorization, 'ROLE_SAFETY');

        self::assertSame('phil_incident_mode_admin', IncidentProposal::ROUTE);
        self::assertTrue($proposal->isOffered());
        self::assertSame(['ROLE_SAFETY'], $authorization->asked);
        self::assertFalse($proposal->isProposed(), 'no surge yet');

        $stage->people->signIn('m-1');

        foreach (['1', '2', '3'] as $id) {
            $stage->reports->report(TargetReference::of('forum.post', $id), 'spam');
        }

        self::assertTrue($proposal->isProposed());

        $authorization->granted = false;
        self::assertFalse($proposal->isOffered());
        self::assertFalse($proposal->isProposed(), 'only to the role of the incident mode');

        $authorization->granted = true;
        $mode->start(Presets::SUSPEND_SIGNUPS, Duration::ofMinutes(90), null);
        self::assertFalse($proposal->isProposed(), 'not while an incident is in force');
    }

    public function testTheTwigFunctions(): void
    {
        $stage = new Stage();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        // A session of its own: the default storage starts PHP's native session, which outlives the test and keeps the
        // kernel of the functional tests run after it from reading their session cookie.
        $requests = new RequestStack([$request]);
        $tokens = new CsrfTokenManager(null, new SessionTokenStorage($requests));
        $extension = new ModerationExtension(new Reasons(['spam']), $stage->hides, $tokens, new KitComponents('ui/kit'));

        self::assertSame(['spam'], $extension->reasons());
        self::assertTrue($tokens->isTokenValid(new CsrfToken('phil_moderation_report', $extension->reportToken())));
        self::assertFalse($extension->mayHide());
        $stage->people->signIn('m-1');
        $stage->people->trusted = ['m-1'];
        self::assertTrue($extension->mayHide());
        self::assertSame('ui:kit:Button', $extension->kit('Button'));
        self::assertSame('Button', new ModerationExtension(new Reasons(), $stage->hides, $tokens)->kit('Button'));
        self::assertSame('Button', new KitComponents('/')->name('Button'));
        self::assertSame('ui:Button', new KitComponents('/ui/')->name('Button'));
    }

    public function testTheTablesJoinTheSchemaOfTheirConnectionOnly(): void
    {
        $connection = $this->connection();
        $listener = new ModerationSchemaListener($connection);
        $schema = Schema::editor()->create();

        $other = new GenerateSchemaEventArgs($this->entityManager($this->connection()), $schema);
        $listener->postGenerateSchema($other);
        self::assertSame($schema, $other->getSchema());

        $event = new GenerateSchemaEventArgs($this->entityManager($connection), $schema);
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(ModerationTables::REPORTS));
    }

    public function testTheBackOfficeHelpers(): void
    {
        self::assertSame('admin_moderation_queue', ModerationAdmin::menuItem()->getAsDto()->getRouteName());
        self::assertSame('back_moderation_queue', ModerationAdmin::menuItem('back')->getAsDto()->getRouteName());
        self::assertSame('Moderation', ModerationAdmin::menuItem()->getAsDto()->getLabel());
        self::assertCount(6, ModerationAdmin::ROUTES);
        self::assertSame('_moderation_decide', ModerationAdmin::ROUTES[AdminController::DECIDE_ROUTE]);
        self::assertNull(ModerationAdmin::dashboardRoute(new Request()));

        $context = self::createStub(AdminContextInterface::class);
        $context->method('getDashboardRouteName')->willReturn('admin');
        $request = new Request();
        $request->attributes->set(ModerationAdmin::CONTEXT_ATTRIBUTE, $context);

        self::assertSame('admin', ModerationAdmin::dashboardRoute($request));
    }

    private function user(string $id): UserInterface&ModerationMember
    {
        return new readonly class($id) implements UserInterface, ModerationMember {
            public function __construct(private string $id)
            {
            }

            public function moderationIdentifier(): string
            {
                return $this->id;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function getUserIdentifier(): string
            {
                return 'user@example.org';
            }
        };
    }

    private function entityManager(Connection $connection): EntityManagerInterface
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    private function connection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
