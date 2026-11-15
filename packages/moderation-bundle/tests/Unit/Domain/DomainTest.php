<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Identifiers;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\Status\Ground;
use Phil\ModerationBundle\Domain\Status\ReportSource;
use Phil\ModerationBundle\Domain\ValueObject\Decision;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\MemberNote;
use Phil\ModerationBundle\Domain\ValueObject\QueueItem;
use Phil\ModerationBundle\Domain\ValueObject\Report;
use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Phil\ModerationBundle\Tests\Fixture\Refusals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemberId::class)]
#[CoversClass(TargetReference::class)]
#[CoversClass(Identifiers::class)]
#[CoversClass(Report::class)]
#[CoversClass(Decision::class)]
#[CoversClass(MemberNote::class)]
#[CoversClass(QueueItem::class)]
#[CoversClass(DecisionAction::class)]
#[CoversClass(Ground::class)]
#[CoversClass(StatementOfReasons::class)]
#[CoversClass(InvalidModeration::class)]
final class DomainTest extends TestCase
{
    use Refusals;

    private const string ID = '0123456789abcdef0123456789abcdef';

    public function testAMemberIsAStableIdentifier(): void
    {
        self::assertSame('m-1', MemberId::of('m-1')->value);
        self::assertSame(str_repeat('x', 190), MemberId::of(str_repeat('x', 190))->value);
        self::assertSame('a@b', MemberId::of('a@b')->value);
        self::assertSame('a@b.c@d', MemberId::of('a@b.c@d')->value);
        self::assertSame('a@b@c.d', MemberId::of('a@b@c.d')->value);
        self::assertNull(MemberId::orNull(null));
        self::assertSame('m-2', MemberId::orNull('m-2')?->value);
        self::assertTrue(MemberId::of('m-1')->equals(MemberId::of('m-1')));
        self::assertFalse(MemberId::of('m-1')->equals(MemberId::of('m-2')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badMembers(): iterable
    {
        yield 'empty' => ['', 'visible characters'];
        yield 'too long' => [str_repeat('x', 191), 'visible characters'];
        yield 'a space' => ['a b', 'visible characters'];
        yield 'a delete' => ["a\x7F", 'visible characters'];
        yield 'an address' => ['someone@example.org', 'never an e-mail address'];
    }

    #[DataProvider('badMembers')]
    public function testAMemberIsRefused(string $value, string $message): void
    {
        self::assertRefused(InvalidModeration::class, $message, static fn (): MemberId => MemberId::of($value));
    }

    public function testATargetIsATypeAndAnIdentifier(): void
    {
        $target = TargetReference::of('forum.post', '42');

        self::assertSame('forum.post', $target->type);
        self::assertSame('42', $target->id);
        self::assertTrue($target->equals(TargetReference::of('forum.post', '42')));
        self::assertFalse($target->equals(TargetReference::of('forum.post', '43')));
        self::assertFalse($target->equals(TargetReference::of('comment', '42')));
        self::assertSame(str_repeat('9', 190), TargetReference::of('a', str_repeat('9', 190))->id);
        self::assertSame('a_b-c.d', TargetReference::of('a_b-c.d', '1')->type);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function badTargets(): iterable
    {
        yield 'an uppercase type' => ['Post', '1', 'is not a target type'];
        yield 'a type too long' => ['a'.str_repeat('b', 64), '1', 'is not a target type'];
        yield 'a type with a newline' => ["post\n", '1', 'is not a target type'];
        yield 'an empty identifier' => ['post', '', 'visible characters'];
        yield 'an identifier too long' => ['post', str_repeat('9', 191), 'visible characters'];
        yield 'an identifier with a space' => ['post', '4 2', 'visible characters'];
    }

    #[DataProvider('badTargets')]
    public function testATargetIsRefused(string $type, string $id, string $message): void
    {
        self::assertRefused(InvalidModeration::class, $message, static fn (): TargetReference => TargetReference::of($type, $id));
    }

    public function testTheIdentifiersAndCodes(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', Identifiers::generate());
        self::assertNotSame(Identifiers::generate(), Identifiers::generate());
        self::assertSame(self::ID, Identifiers::check(self::ID, 'A thing'));
        self::assertRefused(InvalidModeration::class, 'A thing is identified by 32 lowercase hexadecimal characters.', static fn (): string => Identifiers::check('g'.self::ID, 'A thing'));
        self::assertSame('a_b-1', Identifiers::code('a_b-1', 'a code'));
        self::assertNull(Identifiers::text('   ', 10, 'A text'));
        self::assertNull(Identifiers::text(null, 10, 'A text'));
        self::assertSame('éééééééééé', Identifiers::text(' éééééééééé ', 10, 'A text'));
        self::assertRefused(InvalidModeration::class, 'A thing is identified by 32 lowercase hexadecimal characters.', static fn (): string => Identifiers::check(self::ID."\n", 'A thing'));
        self::assertRefused(InvalidModeration::class, '"A" is not a code', static fn (): string => Identifiers::code('A', 'a code'));
        self::assertRefused(InvalidModeration::class, 'A text has at most 10 characters.', static fn (): ?string => Identifiers::text(str_repeat('é', 11), 10, 'A text'));
    }

    public function testAReport(): void
    {
        $at = new DateTimeImmutable('2026-11-13 10:00:00 UTC');
        $report = Report::file(TargetReference::of('comment', '7'), ReportSource::Member, 'spam', MemberId::of('m-1'), '  Buy now  ', $at);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $report->id);
        self::assertSame('Buy now', $report->comment);
        self::assertSame($at, $report->reportedAt);
        self::assertSame('spam', $report->reason);
        self::assertSame(ReportSource::Member, $report->source);
        self::assertTrue($report->isOpen());
        self::assertFalse(new Report(self::ID, $report->target, ReportSource::Member, 'spam', null, null, $at, self::ID)->isOpen());
        self::assertSame(1000, mb_strlen(new Report(self::ID, $report->target, ReportSource::Member, 'spam', null, str_repeat('é', 1000), $at)->comment ?? ''));
        self::assertRefused(InvalidModeration::class, 'A comment has at most 1000 characters.', static fn (): Report => new Report(self::ID, $report->target, ReportSource::Member, 'spam', null, str_repeat('é', 1001), $at));
        self::assertRefused(InvalidModeration::class, 'is not a reason', static fn (): Report => new Report(self::ID, $report->target, ReportSource::Member, 'Spam', null, null, $at));
        self::assertRefused(InvalidModeration::class, 'A decision is identified', static fn (): Report => new Report(self::ID, $report->target, ReportSource::Member, 'spam', null, null, $at, 'x'));
        self::assertRefused(InvalidModeration::class, 'A report is identified', static fn (): Report => new Report('x', $report->target, ReportSource::Member, 'spam', null, null, $at));
    }

    public function testADecision(): void
    {
        $at = new DateTimeImmutable('2026-11-13 10:00:00 UTC');
        $target = TargetReference::of('comment', '7');
        $decision = new Decision(self::ID, $target, DecisionAction::Hide, MemberId::of('m-1'), 'moderator', ' Off topic ', ' Third time ', MemberId::of('m-2'), $at);

        self::assertSame('Off topic', $decision->publicReason);
        self::assertSame('Third time', $decision->note);
        self::assertTrue($decision->public);
        self::assertFalse(new Decision(self::ID, $target, DecisionAction::Hide, null, 'moderator', null, null, null, $at, false)->public);
        self::assertSame(500, mb_strlen(new Decision(self::ID, $target, DecisionAction::Hide, null, 'moderator', str_repeat('é', 500), str_repeat('é', 2000), null, $at)->publicReason ?? ''));
        self::assertRefused(InvalidModeration::class, 'A public reason has at most 500', static fn (): Decision => new Decision(self::ID, $target, DecisionAction::Hide, null, 'moderator', str_repeat('é', 501), null, null, $at));
        self::assertRefused(InvalidModeration::class, 'A note has at most 2000', static fn (): Decision => new Decision(self::ID, $target, DecisionAction::Hide, null, 'moderator', null, str_repeat('é', 2001), null, $at));
        self::assertRefused(InvalidModeration::class, 'is not a role', static fn (): Decision => new Decision(self::ID, $target, DecisionAction::Hide, null, 'Moderator', null, null, null, $at));
        self::assertRefused(InvalidModeration::class, 'A decision is identified', static fn (): Decision => new Decision('x', $target, DecisionAction::Hide, null, 'moderator', null, null, null, $at));
    }

    public function testANote(): void
    {
        $at = new DateTimeImmutable('2026-11-13 10:00:00 UTC');
        $note = new MemberNote(self::ID, MemberId::of('m-2'), MemberId::of('m-1'), '  Warned twice  ', $at);

        self::assertSame('Warned twice', $note->body);
        self::assertSame(5000, mb_strlen(new MemberNote(self::ID, MemberId::of('m-2'), null, str_repeat('é', 5000), $at)->body));
        self::assertRefused(InvalidModeration::class, 'A note is not empty.', static fn (): MemberNote => new MemberNote(self::ID, MemberId::of('m-2'), null, '  ', $at));
        self::assertRefused(InvalidModeration::class, 'A note has at most 5000', static fn (): MemberNote => new MemberNote(self::ID, MemberId::of('m-2'), null, str_repeat('é', 5001), $at));
        self::assertRefused(InvalidModeration::class, 'A note is identified', static fn (): MemberNote => new MemberNote('x', MemberId::of('m-2'), null, 'Hi', $at));
    }

    public function testWhatChangesATargetAndWhatIsHeld(): void
    {
        self::assertSame(
            ['approve', 'hide', 'restore', 'remove'],
            array_values(array_map(static fn (DecisionAction $action): string => $action->value, array_filter(DecisionAction::cases(), static fn (DecisionAction $action): bool => $action->changesTarget()))),
        );

        $at = new DateTimeImmutable('2026-11-13 10:00:00 UTC');
        $target = TargetReference::of('comment', '7');
        self::assertFalse(new QueueItem($target, 1, $at, $at, ['spam'], [ReportSource::Member, ReportSource::TrustedHide])->isHeld());
        self::assertTrue(new QueueItem($target, 1, $at, $at, [], [ReportSource::Sandbox])->isHeld());
        self::assertTrue(new QueueItem($target, 1, $at, $at, [], [ReportSource::Member, ReportSource::WatchedWords])->isHeld());
    }

    public function testWhatRestrictsTheAuthor(): void
    {
        self::assertSame(
            ['hide', 'remove', 'warn'],
            array_values(array_map(static fn (DecisionAction $action): string => $action->value, array_filter(DecisionAction::cases(), static fn (DecisionAction $action): bool => $action->restricts()))),
        );
        self::assertSame(['terms', 'illegal'], array_map(static fn (Ground $ground): string => $ground->value, Ground::cases()));
    }

    public function testAStatementOfReasons(): void
    {
        $at = new DateTimeImmutable('2026-11-13 10:00:00 UTC');
        $make = static fn (string $id = self::ID, string $decision = self::ID, DecisionAction $action = DecisionAction::Hide, string $reason = 'spam', Ground $ground = Ground::Terms, ?string $reference = null, ?string $explanation = ' Facts '): StatementOfReasons => new StatementOfReasons($id, $decision, MemberId::of('m-1'), TargetReference::of('comment', '7'), $action, $reason, $ground, $reference, $explanation, true, false, $at);

        $statement = $make(reference: '  ');

        self::assertSame('Facts', $statement->explanation);
        self::assertNull($statement->reference);
        self::assertSame('A law', $make(ground: Ground::Illegal, reference: ' A law ')->reference);
        self::assertSame(str_repeat('é', 255), $make(reference: str_repeat('é', 255))->reference);
        self::assertSame(str_repeat('é', 2000), $make(explanation: str_repeat('é', 2000))->explanation);

        self::assertRefused(InvalidModeration::class, 'A statement of reasons is identified', static fn (): StatementOfReasons => $make(id: 'x'));
        self::assertRefused(InvalidModeration::class, 'A decision is identified', static fn (): StatementOfReasons => $make(decision: 'x'));
        self::assertRefused(InvalidModeration::class, 'is not a reason', static fn (): StatementOfReasons => $make(reason: 'Spam'));
        self::assertRefused(InvalidModeration::class, 'The action "restore" restricts nothing: no statement of reasons follows it.', static fn (): StatementOfReasons => $make(action: DecisionAction::Restore));
        self::assertRefused(InvalidModeration::class, 'A reference has at most 255', static fn (): StatementOfReasons => $make(reference: str_repeat('é', 256)));
        self::assertRefused(InvalidModeration::class, 'An explanation has at most 2000', static fn (): StatementOfReasons => $make(explanation: str_repeat('é', 2001)));
        self::assertRefused(InvalidModeration::class, 'A statement of reasons explains the facts the decision rests on.', static fn (): StatementOfReasons => $make(explanation: null));
        self::assertRefused(InvalidModeration::class, 'A restriction for illegal content names the law it rests on.', static fn (): StatementOfReasons => $make(ground: Ground::Illegal, reference: ' '));
    }
}
