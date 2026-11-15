<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
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
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalDecisionRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalMemberNoteRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalReportRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalSandboxRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\DbalStatementRepository;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use PHPUnit\Framework\TestCase;

/**
 * What the DBAL stores do, run on SQLite in memory and on the servers.
 */
abstract class RepositoriesTestCase extends TestCase
{
    abstract protected function connection(): Connection;

    public function testTheReportsOfATarget(): void
    {
        $reports = new DbalReportRepository($connection = $this->tables());
        $target = TargetReference::of('forum.post', '1');
        $reports->add($late = $this->report('b', $target, '10:05', 'm-1', 'spam', 'Ads'));
        $reports->add($early = $this->report('a', $target, '10:00', null, 'first-post', null, ReportSource::Sandbox));
        $reports->add($this->report('c', TargetReference::of('forum.post', '2'), '09:00', 'm-1'));

        $open = $reports->openOn($target);

        self::assertSame([$early->id, $late->id], array_map(static fn (Report $report): string => $report->id, $open));
        self::assertSame('2026-11-13T10:05:00+00:00', $open[1]->reportedAt->format(\DATE_ATOM));
        self::assertSame('m-1', $open[1]->reporter?->value);
        self::assertSame('Ads', $open[1]->comment);
        self::assertSame('spam', $open[1]->reason);
        self::assertSame(ReportSource::Sandbox, $open[0]->source);
        self::assertNull($open[0]->reporter);
        self::assertSame($late->id, $reports->openBy($target, MemberId::of('m-1'))?->id);
        self::assertNull($reports->openBy($target, MemberId::of('m-9')));

        self::assertSame(2, $reports->close($target, str_repeat('d', 32)));
        self::assertSame(0, $reports->close($target, str_repeat('e', 32)));
        self::assertSame([], $reports->openOn($target));
        self::assertNull($reports->openBy($target, MemberId::of('m-1')));
        self::assertSame(str_repeat('d', 32), $reports->madeBy(MemberId::of('m-1'))[0]->decisionId);
        self::assertNull($reports->madeBy(MemberId::of('m-1'))[1]->decisionId);
        self::assertCount(1, $connection->fetchAllAssociative('SELECT id FROM '.ModerationTables::REPORTS.' WHERE decision_id IS NULL'));
    }

    public function testTheQueue(): void
    {
        $reports = new DbalReportRepository($this->tables());
        $post = TargetReference::of('forum.post', '1');
        $comment = TargetReference::of('comment', '9');
        $reports->add($this->report('a', $post, '10:00', 'm-1', 'spam'));
        $reports->add($this->report('b', $comment, '09:00', 'm-2', 'hate'));
        $reports->add($this->report('c', $post, '11:00', 'm-2', 'spam', null, ReportSource::WatchedWords));
        $reports->add($this->report('d', $post, '11:30', 'm-3', 'hate'));
        $reports->add($this->report('e', TargetReference::of('listing', '3'), '08:00', 'm-3'));
        $reports->close(TargetReference::of('listing', '3'), str_repeat('f', 32));

        $queue = $reports->queue(10);

        self::assertSame(['comment', 'forum.post'], array_map(static fn (QueueItem $item): string => $item->target->type, $queue));
        self::assertSame(3, $queue[1]->reports);
        self::assertSame('9', $queue[0]->target->id);
        self::assertSame('2026-11-13T10:00:00+00:00', $queue[1]->firstReportedAt->format(\DATE_ATOM));
        self::assertSame('2026-11-13T11:30:00+00:00', $queue[1]->lastReportedAt->format(\DATE_ATOM));
        self::assertSame(['spam', 'hate'], $queue[1]->reasons);
        self::assertSame([ReportSource::Member, ReportSource::WatchedWords], $queue[1]->sources);
        self::assertSame(['hate'], $queue[0]->reasons);
        self::assertSame(['forum.post'], array_map(static fn (QueueItem $item): string => $item->target->type, $reports->queue(10, 1)));
        self::assertSame(['comment'], array_map(static fn (QueueItem $item): string => $item->target->type, $reports->queue(1)));
        self::assertSame(['forum.post'], array_map(static fn (QueueItem $item): string => $item->target->type, $reports->queue(10, 0, ['forum.post', 'listing'])));
        self::assertSame([], $reports->queue(10, 0, ['listing']));
        self::assertSame(2, $reports->countOpenTargets());
        self::assertSame(1, $reports->countOpenTargets(['comment']));
        self::assertSame(0, $reports->countOpenTargets(['listing']));
    }

    public function testCountingAndForgettingTheReporters(): void
    {
        $reports = new DbalReportRepository($this->tables());
        $reports->add($this->report('a', TargetReference::of('comment', '1'), '10:00', 'm-1'));
        $reports->add($this->report('b', TargetReference::of('comment', '2'), '11:00', 'm-1'));
        $reports->add($this->report('c', TargetReference::of('comment', '3'), '11:00', 'm-2'));

        self::assertSame(3, $reports->countSince($this->at('10:00')));
        self::assertSame(2, $reports->countSince($this->at('10:00:01')));
        self::assertSame([str_repeat('b', 32), str_repeat('a', 32)], array_map(static fn (Report $report): string => $report->id, $reports->madeBy(MemberId::of('m-1'))));
        self::assertSame(2, $reports->forgetReporter(MemberId::of('m-1')));
        self::assertSame([], $reports->madeBy(MemberId::of('m-1')));
        self::assertNull($reports->openOn(TargetReference::of('comment', '1'))[0]->reporter);
        self::assertSame('m-2', $reports->openOn(TargetReference::of('comment', '3'))[0]->reporter?->value);
    }

    public function testTheDecisions(): void
    {
        $decisions = new DbalDecisionRepository($this->tables());
        $post = TargetReference::of('forum.post', '1');
        $decisions->add($first = $this->decision('a', $post, '10:00', 'mod-1', 'author', true));
        $decisions->add($second = $this->decision('b', $post, '11:00', 'mod-2', 'author', false));
        $decisions->add($other = $this->decision('c', TargetReference::of('comment', '2'), '12:00', 'mod-1', 'someone', true));

        $on = $decisions->on($post);

        self::assertSame([$second->id, $first->id], array_map(static fn (Decision $decision): string => $decision->id, $on));
        self::assertSame(DecisionAction::Hide, $on[1]->action);
        self::assertSame('mod-1', $on[1]->moderator?->value);
        self::assertSame('moderator', $on[1]->role);
        self::assertSame('Spam', $on[1]->publicReason);
        self::assertSame('Note', $on[1]->note);
        self::assertSame('author', $on[1]->author?->value);
        self::assertTrue($on[1]->public);
        self::assertFalse($on[0]->public);
        self::assertSame('2026-11-13T10:00:00+00:00', $on[1]->decidedAt->format(\DATE_ATOM));
        self::assertSame([$second->id, $first->id], array_map(static fn (Decision $decision): string => $decision->id, $decisions->about(MemberId::of('author'))));
        self::assertSame([$other->id, $first->id], array_map(static fn (Decision $decision): string => $decision->id, $decisions->takenBy(MemberId::of('mod-1'))));
        self::assertSame([$other->id, $first->id], array_map(static fn (Decision $decision): string => $decision->id, $decisions->public(10)));
        self::assertSame([$first->id], array_map(static fn (Decision $decision): string => $decision->id, $decisions->public(10, 1)));
        self::assertSame([$other->id], array_map(static fn (Decision $decision): string => $decision->id, $decisions->public(1)));
        self::assertSame(2, $decisions->countPublic());

        self::assertSame(2, $decisions->forget(MemberId::of('mod-1')));
        self::assertSame([], $decisions->takenBy(MemberId::of('mod-1')));
        self::assertSame('moderator', $decisions->on($post)[1]->role);
        self::assertSame(2, $decisions->forget(MemberId::of('author')));
        self::assertSame([], $decisions->about(MemberId::of('author')));
        self::assertSame('mod-2', $decisions->on($post)[0]->moderator?->value);
        self::assertSame(0, $decisions->forget(MemberId::of('nobody')));
    }

    public function testTheNotes(): void
    {
        $notes = new DbalMemberNoteRepository($this->tables());
        $member = MemberId::of('m-2');
        $notes->add($first = new MemberNote(str_repeat('a', 32), $member, MemberId::of('mod-1'), 'Once', $this->at('10:00')));
        $notes->add($second = new MemberNote(str_repeat('b', 32), $member, null, 'Twice', $this->at('11:00')));
        $notes->add(new MemberNote(str_repeat('c', 32), MemberId::of('m-3'), $member, 'By m-2', $this->at('11:00')));

        self::assertSame([$second->id, $first->id], array_map(static fn (MemberNote $note): string => $note->id, $notes->about($member)));
        self::assertSame('Once', $notes->find($first->id)?->body);
        self::assertSame('mod-1', $notes->find($first->id)?->author?->value);
        self::assertSame('2026-11-13T10:00:00+00:00', $notes->find($first->id)?->writtenAt->format(\DATE_ATOM));
        self::assertNull($notes->find(str_repeat('f', 32)));
        self::assertTrue($notes->remove($first->id));
        self::assertFalse($notes->remove($first->id));
        self::assertSame(2, $notes->erase($member));
        self::assertSame([], $notes->about($member));
        self::assertNull($notes->find(str_repeat('c', 32))?->author);
        self::assertSame('By m-2', $notes->find(str_repeat('c', 32))?->body);
    }

    public function testTheSandbox(): void
    {
        $sandbox = new DbalSandboxRepository($this->tables());
        $member = MemberId::of('m-1');

        self::assertNull($sandbox->clearedAt($member));
        $sandbox->clear($member, new DateTimeImmutable('2026-11-13 05:00:00', new DateTimeZone('America/Toronto')));
        $sandbox->clear($member, $this->at('12:00'));

        self::assertSame('2026-11-13T10:00:00+00:00', $sandbox->clearedAt($member)?->format(\DATE_ATOM));
        self::assertTrue($sandbox->forget($member));
        self::assertFalse($sandbox->forget($member));
        self::assertNull($sandbox->clearedAt($member));
    }

    public function testARowThatNoLongerReadsIsLeftOut(): void
    {
        $connection = $this->tables();
        $reports = new DbalReportRepository($connection);
        $decisions = new DbalDecisionRepository($connection);
        $notes = new DbalMemberNoteRepository($connection);
        $row = ['target_type' => 'comment', 'target_id' => '1', 'reason' => 'spam', 'reported_at' => '2026-11-13 10:00:00'];
        $connection->insert(ModerationTables::REPORTS, ['id' => str_repeat('a', 32), 'source' => 'storm'] + $row);
        $connection->insert(ModerationTables::REPORTS, ['id' => str_repeat('b', 32), 'source' => 'member', 'reporter' => 'x@example.org'] + $row);
        $connection->insert(ModerationTables::REPORTS, ['id' => str_repeat('c', 32), 'source' => 'member', 'target_type' => 'Bad'] + $row);
        $connection->insert(ModerationTables::DECISIONS, ['id' => str_repeat('a', 32), 'target_type' => 'comment', 'target_id' => '1', 'action' => 'storm', 'role' => 'moderator', 'decided_at' => '2026-11-13 10:00:00', 'is_public' => 1]);
        $connection->insert(ModerationTables::DECISIONS, ['id' => str_repeat('b', 32), 'target_type' => 'comment', 'target_id' => '1', 'action' => 'hide', 'role' => 'Bad role', 'decided_at' => '2026-11-13 10:00:00', 'is_public' => 1]);
        $connection->insert(ModerationTables::NOTES, ['id' => str_repeat('a', 32), 'member' => 'x@example.org', 'body' => 'Hi', 'written_at' => '2026-11-13 10:00:00']);

        self::assertSame([], $reports->openOn(TargetReference::of('comment', '1')));
        self::assertSame(['comment'], array_map(static fn (QueueItem $item): string => $item->target->type, $reports->queue(10)));
        self::assertSame([], $reports->queue(10)[0]->sources === [ReportSource::Member] ? [] : ['sources']);
        self::assertSame([], $decisions->on(TargetReference::of('comment', '1')));
        self::assertNull($notes->find(str_repeat('a', 32)));
    }

    public function testTheQueueHoldsEachReasonAndSourceOnceInAList(): void
    {
        $reports = new DbalReportRepository($this->tables());
        $post = TargetReference::of('forum.post', '1');
        $reports->add($this->report('a', $post, '10:00', 'm-1', 'spam'));
        $reports->add($this->report('b', $post, '10:01', 'm-2', 'spam'));
        $reports->add($this->report('c', $post, '10:02', 'm-3', 'hate', null, ReportSource::WatchedWords));

        $item = $reports->queue(10)[0];

        self::assertSame(['spam', 'hate'], $item->reasons);
        self::assertSame([ReportSource::Member, ReportSource::WatchedWords], $item->sources);
    }

    public function testTheListsKeepNoGapWhereARowWasLeftOut(): void
    {
        $connection = $this->tables();
        $reports = new DbalReportRepository($connection);
        $decisions = new DbalDecisionRepository($connection);
        $notes = new DbalMemberNoteRepository($connection);
        $comment = TargetReference::of('comment', '1');
        $row = ['target_type' => 'comment', 'target_id' => '1', 'reason' => 'spam', 'reported_at' => '2026-11-13 10:00:00'];
        $connection->insert(ModerationTables::REPORTS, ['id' => str_repeat('a', 32), 'source' => 'storm'] + $row);
        $reports->add($this->report('b', $comment, '10:00', 'm-1'));
        $connection->insert(ModerationTables::DECISIONS, ['id' => str_repeat('f', 32), 'target_type' => 'comment', 'target_id' => '1', 'action' => 'storm', 'role' => 'moderator', 'decided_at' => '2026-11-13 12:00:00', 'is_public' => 1]);
        $decisions->add($this->decision('b', $comment, '10:00', 'mod-1', 'author', true));
        $connection->insert(ModerationTables::NOTES, ['id' => str_repeat('f', 32), 'member' => 'm-1', 'author' => 'x@example.org', 'body' => 'Hi', 'written_at' => '2026-11-13 12:00:00']);
        $notes->add(new MemberNote(str_repeat('b', 32), MemberId::of('m-1'), null, 'Once', $this->at('10:00')));

        self::assertCount(1, $reports->openOn($comment));
        self::assertList($reports->openOn($comment));
        self::assertList($decisions->on($comment));
        self::assertList($decisions->public(10));
        self::assertCount(1, $decisions->public(10));
        self::assertList($notes->about(MemberId::of('m-1')));
        self::assertCount(1, $notes->about(MemberId::of('m-1')));
    }

    public function testADecisionOfAForgottenModeratorAndAuthorIsKept(): void
    {
        $decisions = new DbalDecisionRepository($this->tables());
        $post = TargetReference::of('forum.post', '1');
        $decisions->add(new Decision(str_repeat('a', 32), $post, DecisionAction::Hide, null, 'moderator', null, null, null, $this->at('10:00'), false));

        $found = $decisions->on($post);

        self::assertCount(1, $found);
        self::assertNull($found[0]->moderator);
        self::assertNull($found[0]->author);
    }

    public function testTheStatementsOfReasons(): void
    {
        $connection = $this->tables();
        $statements = new DbalStatementRepository($connection);
        $statements->add($first = $this->statement('a', 'b', 'm-1', '10:00', true, false, 'Rule 2'));
        $statements->add($second = $this->statement('c', 'd', 'm-1', '11:00', false, true, null, Ground::Illegal, 'A law'));
        $statements->add($this->statement('e', 'f', 'm-2', '12:00'));

        $found = $statements->find($first->id);

        self::assertInstanceOf(StatementOfReasons::class, $found);
        self::assertSame(str_repeat('b', 32), $found->decisionId);
        self::assertSame('m-1', $found->recipient->value);
        self::assertSame('forum.post', $found->target->type);
        self::assertSame('1', $found->target->id);
        self::assertSame(DecisionAction::Hide, $found->action);
        self::assertSame('spam', $found->reason);
        self::assertSame(Ground::Terms, $found->ground);
        self::assertSame('Rule 2', $found->reference);
        self::assertSame('The facts', $found->explanation);
        self::assertTrue($found->prompted);
        self::assertFalse($found->automatedDetection);
        self::assertSame('2026-11-13T10:00:00+00:00', $found->issuedAt->format(\DATE_ATOM));
        $illegal = $statements->forDecision(str_repeat('d', 32));
        self::assertInstanceOf(StatementOfReasons::class, $illegal);
        self::assertSame(Ground::Illegal, $illegal->ground);
        self::assertFalse($illegal->prompted);
        self::assertTrue($illegal->automatedDetection);
        self::assertNull($statements->find(str_repeat('9', 32)));
        self::assertNull($statements->forDecision(str_repeat('9', 32)));

        $member = MemberId::of('m-1');
        self::assertSame([$second->id, $first->id], array_map(static fn (StatementOfReasons $s): string => $s->id, $statements->addressedTo($member, 10)));
        self::assertSame([$second->id], array_map(static fn (StatementOfReasons $s): string => $s->id, $statements->addressedTo($member, 1)));
        self::assertSame([$first->id], array_map(static fn (StatementOfReasons $s): string => $s->id, $statements->addressedTo($member, 1, 1)));
        self::assertSame(2, $statements->countAddressedTo($member));
        self::assertSame(0, $statements->countAddressedTo(MemberId::of('m-9')));

        self::assertSame(2, $statements->erase($member));
        self::assertSame(0, $statements->erase($member));
        self::assertSame([], $statements->addressedTo($member, 10));
        self::assertSame(1, $statements->countAddressedTo(MemberId::of('m-2')));
    }

    public function testAStatementThatNoLongerReadsIsLeftOut(): void
    {
        $connection = $this->tables();
        $statements = new DbalStatementRepository($connection);
        $row = [
            'recipient' => 'm-1', 'target_type' => 'comment', 'target_id' => '1', 'action' => 'hide', 'reason' => 'spam', 'ground' => 'terms',
            'explanation' => 'Facts', 'prompted' => 0, 'automated_detection' => 0, 'issued_at' => '2026-11-13 10:00:00',
        ];
        $connection->insert(ModerationTables::STATEMENTS, ['id' => str_repeat('a', 32), 'decision_id' => str_repeat('a', 32), 'action' => 'storm'] + $row);
        $connection->insert(ModerationTables::STATEMENTS, ['id' => str_repeat('b', 32), 'decision_id' => str_repeat('b', 32), 'ground' => 'whim'] + $row);
        $connection->insert(ModerationTables::STATEMENTS, ['id' => str_repeat('c', 32), 'decision_id' => str_repeat('c', 32), 'action' => 'approve'] + $row);

        $statements->add($this->statement('d', 'd', 'm-1', '09:00'));

        self::assertNull($statements->find(str_repeat('a', 32)));
        self::assertNull($statements->find(str_repeat('b', 32)));
        self::assertNull($statements->find(str_repeat('c', 32)));
        self::assertSame([str_repeat('d', 32)], array_map(static fn (StatementOfReasons $s): string => $s->id, $statements->addressedTo(MemberId::of('m-1'), 10)));
        self::assertSame(4, $statements->countAddressedTo(MemberId::of('m-1')));
    }

    public function testForgettingAMemberOfTheSandboxLeavesTheOthers(): void
    {
        $sandbox = new DbalSandboxRepository($this->tables());
        $sandbox->clear(MemberId::of('m-1'), $this->at('10:00'));
        $sandbox->clear(MemberId::of('m-2'), $this->at('10:00'));

        self::assertTrue($sandbox->forget(MemberId::of('m-1')));
        self::assertNull($sandbox->clearedAt(MemberId::of('m-1')));
        self::assertInstanceOf(DateTimeImmutable::class, $sandbox->clearedAt(MemberId::of('m-2')));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    protected static function assertList(array $values): void
    {
        self::assertTrue(array_is_list($values));
    }

    protected function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13 '.$time.(5 === \strlen($time) ? ':00' : '').' UTC');
    }

    private function tables(): Connection
    {
        $connection = $this->connection();
        $manager = $connection->createSchemaManager();

        foreach (ModerationTables::tables() as $table) {
            $name = $table->getObjectName()->toString();

            if ($manager->tablesExist([$name])) {
                $manager->dropTable($name);
            }

            $manager->createTable($table);
        }

        return $connection;
    }

    private function report(string $letter, TargetReference $target, string $time, ?string $reporter, string $reason = 'spam', ?string $comment = null, ReportSource $source = ReportSource::Member): Report
    {
        return new Report(str_repeat($letter, 32), $target, $source, $reason, MemberId::orNull($reporter), $comment, $this->at($time));
    }

    private function statement(string $letter, string $decision, string $recipient, string $time, bool $prompted = false, bool $automated = false, ?string $reference = null, Ground $ground = Ground::Terms, ?string $law = null): StatementOfReasons
    {
        return new StatementOfReasons(str_repeat($letter, 32), str_repeat($decision, 32), MemberId::of($recipient), TargetReference::of('forum.post', '1'), DecisionAction::Hide, 'spam', $ground, $law ?? $reference, 'The facts', $prompted, $automated, $this->at($time));
    }

    private function decision(string $letter, TargetReference $target, string $time, string $moderator, string $author, bool $public): Decision
    {
        return new Decision(str_repeat($letter, 32), $target, DecisionAction::Hide, MemberId::of($moderator), 'moderator', 'Spam', 'Note', MemberId::of($author), $this->at($time), $public);
    }
}
