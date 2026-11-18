<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\IncidentMode\Activation;
use Phil\IncidentMode\Actor;
use Phil\IncidentMode\Dbal\IncidentTables;
use Phil\IncidentMode\Event\IncidentExtended;
use Phil\IncidentMode\Event\IncidentLifted;
use Phil\IncidentMode\Event\IncidentStarted;
use Phil\IncidentMode\LiftReason;
use Phil\IncidentMode\Messenger\LiftExpiredIncidents;
use Phil\IncidentMode\Presets;
use Phil\IncidentModeBundle\Application\DurationInput;
use Phil\IncidentModeBundle\Attribute\SuspendedDuring;
use Phil\IncidentModeBundle\Infrastructure\Audit\AuditIncidents;
use Phil\IncidentModeBundle\Infrastructure\Privacy\IncidentPrivacy;
use Phil\IncidentModeBundle\Infrastructure\Scheduler\IncidentSchedule;
use Phil\IncidentModeBundle\Infrastructure\Security\SecurityCurrentMember;
use Phil\IncidentModeBundle\Infrastructure\Twig\KitComponents;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\AuditRows;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\SortedJson;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Stage;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(AuditIncidents::class)]
#[CoversClass(IncidentPrivacy::class)]
#[CoversClass(IncidentSchedule::class)]
#[CoversClass(SecurityCurrentMember::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(SuspendedDuring::class)]
final class IntegrationsTest extends TestCase
{
    public function testTheIncidentsAreWrittenToTheTrailWithoutTheirReason(): void
    {
        $rows = new AuditRows();
        $audit = new AuditIncidents(new AuditTrail($rows, new SortedJson(), new MockClock('2026-11-17 10:00 UTC')), 'safety');
        $stage = new Stage();
        $started = $stage->start(Presets::SUSPEND_CONTRIBUTIONS, '1h');
        $extended = $started->endingAt(new DateTimeImmutable('2026-11-17 12:00 UTC'));
        $long = Actor::of(str_repeat('a', 129));

        $audit->started(new IncidentStarted($started));
        $audit->extended(new IncidentExtended($extended, $started->endsAt, Actor::of('ops-2')));
        $audit->lifted(new IncidentLifted($extended->liftedOn(new DateTimeImmutable('2026-11-17 11:30 UTC'), Actor::of('ops:3'), LiftReason::Manual)));
        $audit->lifted(new IncidentLifted($extended->liftedOn($extended->endsAt, null, LiftReason::Expired)));
        $audit->extended(new IncidentExtended($extended, $started->endsAt, $long));
        $audit->extended(new IncidentExtended($extended, $started->endsAt, Actor::of('ops/2')));

        $written = $rows->rows;
        self::assertSame(['incident.started', 'incident.extended', 'incident.lifted', 'incident.expired', 'incident.extended', 'incident.extended'], array_map(static fn (AuditRow $row): ?string => $row->facts->action, $written));
        self::assertSame(['admin-1', 'ops-2', 'ops:3', null, null, null], array_map(static fn (AuditRow $row): ?string => $row->facts->actor, $written));
        self::assertSame([$started->id], array_values(array_unique(array_map(static fn (AuditRow $row): ?string => $row->facts->subject?->id, $written))));
        self::assertSame('incident', $written[0]->facts->subject?->type);
        self::assertSame(['safety'], array_values(array_unique(array_map(static fn (AuditRow $row): string => $row->segment, $written))));
        self::assertSame('{"ends_at":"2026-11-17T11:00:00+00:00","preset":"suspend-contributions","restrictions":"signups,invitations,new-messages,new-posts,new-comments,uploads"}', $written[0]->payload);
        self::assertSame('{"ends_at":"2026-11-17T12:00:00+00:00","preset":"suspend-contributions","previous_end":"2026-11-17T11:00:00+00:00"}', $written[1]->payload);
        self::assertSame('{"lifted_at":"2026-11-17T11:30:00+00:00","preset":"suspend-contributions"}', $written[2]->payload);

        foreach ($written as $row) {
            self::assertStringNotContainsString('Spam wave', $row->payload);
        }

        self::assertSame('incidents', AuditIncidents::DEFAULT_SEGMENT);
        new AuditIncidents(new AuditTrail($rows, new SortedJson(), new MockClock('2026-11-17 10:00 UTC')))->started(new IncidentStarted($started));
        self::assertSame('incidents', $rows->rows[6]->segment);
    }

    public function testALiftWithoutAMomentIsWrittenWithoutOne(): void
    {
        $rows = new AuditRows();
        $started = new Stage()->start(Presets::READ_ONLY, '30m');

        new AuditIncidents(new AuditTrail($rows, new SortedJson(), new MockClock('2026-11-17 10:00 UTC')))->lifted(new IncidentLifted($started));

        self::assertSame('{"lifted_at":null,"preset":"read-only"}', $rows->rows[0]->payload);
    }

    public function testAnIncidentStartedByNobodyIsExportedAsNotStartedByTheMember(): void
    {
        $stage = new Stage();
        $started = $stage->incidents->start(Presets::SUSPEND_SIGNUPS, DurationInput::parse('1h'), null);
        $stage->incidents->lift($started->id, Actor::of('ada'));

        $export = new IncidentPrivacy($stage->store)->export(DataSubject::fromString('ada'));

        self::assertSame(['incidents' => [
            ['preset' => 'suspend-signups', 'restrictions' => ['signups'], 'started_at' => '2026-11-17T10:00:00+00:00', 'ends_at' => '2026-11-17T11:00:00+00:00', 'lifted_at' => '2026-11-17T10:00:00+00:00', 'started_by_me' => false, 'lifted_by_me' => true],
        ]], $export);
    }

    public function testTheIncidentsOfAMemberAreExportedAndErased(): void
    {
        $stage = new Stage();
        $started = $stage->incidents->start(Presets::SUSPEND_SIGNUPS, DurationInput::parse('1h'), Actor::of('ada'), 'A reason');
        $stage->clock->modify('+1 minute');
        $lifted = $stage->start(Presets::SUSPEND_INVITATIONS, '1h');
        $stage->incidents->lift($lifted->id, Actor::of('ada'));
        $stage->start(Presets::READ_ONLY, '30m');

        $privacy = new IncidentPrivacy($stage->store);

        self::assertSame('incident_mode', $privacy->module()->value);
        self::assertSame(['incidents' => [
            ['preset' => 'suspend-invitations', 'restrictions' => ['invitations'], 'started_at' => '2026-11-17T10:01:00+00:00', 'ends_at' => '2026-11-17T11:01:00+00:00', 'lifted_at' => '2026-11-17T10:01:00+00:00', 'started_by_me' => false, 'lifted_by_me' => true],
            ['preset' => 'suspend-signups', 'restrictions' => ['signups'], 'started_at' => '2026-11-17T10:00:00+00:00', 'ends_at' => '2026-11-17T11:00:00+00:00', 'lifted_at' => null, 'started_by_me' => true, 'lifted_by_me' => false],
        ]], $privacy->export(DataSubject::fromString('ada')));
        self::assertSame([], $privacy->export(DataSubject::fromString('bob')));
        self::assertSame([], $privacy->export(DataSubject::fromString('ada@example.org')));

        $report = $privacy->erase(DataSubject::fromString('ada'));
        self::assertSame('incident_mode', $report->module->value);
        self::assertSame([IncidentTables::ACTIVATIONS => ErasureEffect::Pseudonymised], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('ada')));
        self::assertNull($stage->incidents->find($started->id)?->startedBy);
        self::assertNull($stage->incidents->find($lifted->id)?->liftedBy);
        self::assertSame('admin-1', $stage->incidents->find($lifted->id)?->startedBy?->id);
        self::assertInstanceOf(Activation::class, $stage->incidents->find($started->id));

        self::assertSame([IncidentTables::ACTIVATIONS => ErasureEffect::Pseudonymised], $privacy->erase(DataSubject::fromString('ada'))->effects);
        self::assertSame([IncidentTables::ACTIVATIONS => ErasureEffect::Pseudonymised], $privacy->erase(DataSubject::fromString('ada@example.org'))->effects);
    }

    public function testTheSweepRunsAtTheConfiguredFrequency(): void
    {
        $messages = new IncidentSchedule('10 minutes')->getSchedule()->getRecurringMessages();
        self::assertCount(1, $messages);
        $trigger = $messages[0]->getTrigger();
        self::assertInstanceOf(TriggerInterface::class, $trigger);
        $now = new DateTimeImmutable('2026-11-17 10:00 UTC');
        self::assertSame(600, ($trigger->getNextRunDate($now)?->getTimestamp() ?? 0) - $now->getTimestamp());
        self::assertEquals([new LiftExpiredIncidents()], iterator_to_array($messages[0]->getMessages(new MessageContext('incident_mode', 'id', $trigger, $now)), false));

        $default = new IncidentSchedule()->getSchedule()->getRecurringMessages()[0]->getTrigger();
        self::assertSame(300, ($default->getNextRunDate($now)?->getTimestamp() ?? 0) - $now->getTimestamp());
        self::assertSame('incident_mode', IncidentSchedule::DEFAULT_NAME);
    }

    public function testTheMemberIsTheUserSignedIn(): void
    {
        $tokens = new TokenStorage();
        $member = new SecurityCurrentMember($tokens);
        self::assertNull($member->identifier());
        self::assertNull(new SecurityCurrentMember()->identifier());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada', null), 'main'));
        self::assertSame('ada', $member->identifier());
    }

    public function testTheRecipesAreNamedAfterTheSubdirectory(): void
    {
        self::assertSame('Button', new KitComponents()->name('Button'));
        self::assertSame('ui:Button', new KitComponents('ui')->name('Button'));
        self::assertSame('ui:kit:Alert:Title', new KitComponents('/ui/kit/')->name('Alert:Title'));
        self::assertSame('Button', new KitComponents('/')->name('Button'));
    }

    public function testTheAttributeCoversTheMethodsNamed(): void
    {
        $every = new SuspendedDuring('signups');
        self::assertSame(['signups'], $every->restrictions);
        self::assertSame([], $every->methods);
        self::assertTrue($every->covers('GET'));

        $posts = new SuspendedDuring(['signups', 'invitations'], ['post', 'Put']);
        self::assertSame(['signups', 'invitations'], $posts->restrictions);
        self::assertSame(['POST', 'PUT'], $posts->methods);
        self::assertTrue($posts->covers('post'));
        self::assertTrue($posts->covers('PUT'));
        self::assertFalse($posts->covers('GET'));
        self::assertSame(['a', 'b'], new SuspendedDuring([3 => 'a', 7 => 'b'], [2 => 'get'])->restrictions);
        self::assertSame(['GET'], new SuspendedDuring('a', [2 => 'get'])->methods);
    }
}
