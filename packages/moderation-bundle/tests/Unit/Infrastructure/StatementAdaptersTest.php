<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\IncidentMode;
use Phil\IncidentMode\IncidentRestrictions;
use Phil\IncidentMode\InMemoryActivationStore;
use Phil\IncidentMode\Presets;
use Phil\ModerationBundle\Application\Event\StatementIssued;
use Phil\ModerationBundle\Application\Grounds;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\Status\Ground;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use Phil\ModerationBundle\Infrastructure\IncidentMode\CurrentMemberRestrictions;
use Phil\ModerationBundle\Infrastructure\Notifications\StatementNotifier;
use Phil\ModerationBundle\Infrastructure\Notifications\StatementSubjects;
use Phil\ModerationBundle\Infrastructure\Privacy\ModerationPrivacy;
use Phil\ModerationBundle\Tests\Fixture\Stage;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(CurrentMemberRestrictions::class)]
#[CoversClass(StatementNotifier::class)]
#[CoversClass(StatementSubjects::class)]
#[CoversClass(ModerationPrivacy::class)]
final class StatementAdaptersTest extends TestCase
{
    public function testAnIncidentSparesTheCurrentMemberWhenTheyAreEstablished(): void
    {
        $stage = new Stage();
        $mode = new IncidentMode(Presets::defaults(), new InMemoryActivationStore(), $stage->clock);
        $restrictions = new CurrentMemberRestrictions(new IncidentRestrictions($mode, $stage->people, ['new-posts', 'signups']), $stage->people);
        $stage->people->established = ['old-timer'];
        $stage->people->signIn('old-timer');

        self::assertFalse($restrictions->appliesNow('new-posts'), 'nothing applies outside an incident');

        $mode->start(Presets::SUSPEND_CONTRIBUTIONS, Duration::ofHours(1), null);

        self::assertFalse($restrictions->appliesNow('new-posts'), 'an established member is spared');
        self::assertTrue($restrictions->appliesNow('new-comments'), 'a restriction not listed spares nobody');
        $stage->people->signIn('newcomer');
        self::assertTrue($restrictions->appliesNow('new-posts'));
        $stage->people->established = ['old-timer', 'newcomer'];
        self::assertFalse($restrictions->appliesNow('new-posts'), 'the member is read on every call');
        $stage->people->signOut();
        self::assertTrue($restrictions->appliesNow('new-posts'), 'a visitor is never spared');
    }

    public function testTheMemberIsNotifiedOfTheirStatementByItsIdentifier(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            #[Override]
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };
        // Never called: the bus takes every message.
        $handler = new ReflectionClass(SendNotificationHandler::class)->newInstanceWithoutConstructor();
        $notifier = new StatementNotifier(new Notifications(new NotificationTypes(new NotificationType('moderation.statement')), $handler, $bus), 'moderation.statement');
        $statement = $this->statement('author');

        $notifier(new StatementIssued($statement));
        $notifier(new StatementIssued($this->statement('member/slash')));

        self::assertCount(1, $bus->messages);
        $message = $bus->messages[0];
        self::assertInstanceOf(SendNotification::class, $message);
        self::assertSame('moderation.statement', $message->type);
        self::assertSame(['author'], $message->recipients);
        self::assertSame(StatementSubjects::SUBJECT, $message->subjectType);
        self::assertSame($statement->id, $message->subjectId);
    }

    public function testTheSubjectOfTheNotificationIsTheStatementPage(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource('php', \dirname(__DIR__, 3).'/translations/phil_moderation.en.php', 'en', 'phil_moderation');
        $translator->addResource('php', \dirname(__DIR__, 3).'/translations/phil_moderation.fr.php', 'fr', 'phil_moderation');

        $routes = new RouteCollection();
        $routes->add('phil_moderation_statement', new Route('/moderation/statements/{id}'));

        $subjects = new StatementSubjects($translator, new UrlGenerator($routes, new RequestContext(host: 'example.org')));
        $id = str_repeat('a', 32);

        $resolved = $subjects->resolve(new SubjectReference(StatementSubjects::SUBJECT, $id), 'fr');

        self::assertSame('Avis motivé', $resolved?->label);
        self::assertSame('http://example.org/moderation/statements/'.$id, $resolved->url);
        self::assertSame('Statement of reasons', $subjects->resolve(new SubjectReference(StatementSubjects::SUBJECT, $id), null)?->label);
        self::assertNull($subjects->resolve(new SubjectReference('comment', '1'), 'en'));

        $withoutRoutes = new StatementSubjects($translator, new UrlGenerator(new RouteCollection(), new RequestContext()));
        self::assertNull($withoutRoutes->resolve(new SubjectReference(StatementSubjects::SUBJECT, $id), 'en')?->url);
    }

    public function testTheStatementsOfAMemberInTheirPrivacy(): void
    {
        $stage = new Stage();
        $stage->posts->contents['1'] = new TargetView('A post', author: MemberId::of('m-1'));
        $stage->people->signIn('mod-1', $stage->moderator);
        $count = 2 * ModerationPrivacy::PAGE + 1;

        for ($i = 0; $i < $count; ++$i) {
            $stage->reasoned->decide(TargetReference::of('forum.post', '1'), DecisionAction::Warn, grounds: new Grounds('illegal', 'Facts '.$i, 'A law'));
            $stage->clock->sleep(1);
        }

        $privacy = new ModerationPrivacy($stage->reportRepository, $stage->decisionRepository, $stage->noteRepository, $stage->sandboxRepository, null, $stage->statementRepository);
        $statements = $privacy->export(DataSubject::fromString('m-1'))['statements_addressed_to_me'] ?? [];

        self::assertIsArray($statements);
        self::assertCount($count, $statements);
        self::assertSame([
            'target_type' => 'forum.post', 'target_id' => '1', 'action' => 'warn', 'reason' => 'illegal', 'ground' => 'illegal',
            'reference' => 'A law', 'explanation' => 'Facts 200', 'prompted_by_a_report' => false, 'automated_detection' => false,
            'issued_at' => '2026-11-13T10:03:20+00:00',
        ], $statements[0]);
        self::assertStringNotContainsString('mod-1', json_encode($statements, \JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('statements_addressed_to_me', $privacy->export(DataSubject::fromString('mod-1')));

        $report = $privacy->erase(DataSubject::fromString('m-1'));

        self::assertSame(ErasureEffect::Deleted, $report->effects[ModerationTables::STATEMENTS]);
        self::assertSame(0, $stage->statementRepository->countAddressedTo(MemberId::of('m-1')));

        $without = new ModerationPrivacy($stage->reportRepository, $stage->decisionRepository, $stage->noteRepository, $stage->sandboxRepository);
        self::assertArrayNotHasKey(ModerationTables::STATEMENTS, $without->erase(DataSubject::fromString('m-1'))->effects);
    }

    private function statement(string $recipient): StatementOfReasons
    {
        return new StatementOfReasons(
            str_repeat('b', 32),
            str_repeat('c', 32),
            MemberId::of($recipient),
            TargetReference::of('forum.post', '1'),
            DecisionAction::Hide,
            'spam',
            Ground::Terms,
            null,
            'Ads',
            false,
            false,
            new DateTimeImmutable('2026-11-13 10:00:00 UTC'),
        );
    }
}
