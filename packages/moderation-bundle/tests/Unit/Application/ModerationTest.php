<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit\Application;

use Phil\ModerationBundle\Application\Decisions;
use Phil\ModerationBundle\Application\Event\ContentHeld;
use Phil\ModerationBundle\Application\Event\ContentHiddenByMember;
use Phil\ModerationBundle\Application\Event\DecisionTaken;
use Phil\ModerationBundle\Application\Event\ReportFiled;
use Phil\ModerationBundle\Application\Exception\ModerationRefused;
use Phil\ModerationBundle\Application\Exception\UnknownTarget;
use Phil\ModerationBundle\Application\JournalEntry;
use Phil\ModerationBundle\Application\MemberNotes;
use Phil\ModerationBundle\Application\ModerationQueue;
use Phil\ModerationBundle\Application\PublicJournal;
use Phil\ModerationBundle\Application\QueueEntry;
use Phil\ModerationBundle\Application\Reasons;
use Phil\ModerationBundle\Application\Reports;
use Phil\ModerationBundle\Application\ReportSurge;
use Phil\ModerationBundle\Application\ScreeningVerdict;
use Phil\ModerationBundle\Application\Submissions;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Application\TemporaryHides;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\Status\ReportSource;
use Phil\ModerationBundle\Domain\ValueObject\Decision;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\MemberNote;
use Phil\ModerationBundle\Domain\ValueObject\QueueItem;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Phil\ModerationBundle\Tests\Fixture\Refusals;
use Phil\ModerationBundle\Tests\Fixture\ScriptedWords;
use Phil\ModerationBundle\Tests\Fixture\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reports::class)]
#[CoversClass(Decisions::class)]
#[CoversClass(ModerationQueue::class)]
#[CoversClass(QueueEntry::class)]
#[CoversClass(Submissions::class)]
#[CoversClass(TemporaryHides::class)]
#[CoversClass(MemberNotes::class)]
#[CoversClass(PublicJournal::class)]
#[CoversClass(JournalEntry::class)]
#[CoversClass(ReportSurge::class)]
#[CoversClass(ReportFiled::class)]
#[CoversClass(DecisionTaken::class)]
#[CoversClass(ContentHeld::class)]
#[CoversClass(ContentHiddenByMember::class)]
#[CoversClass(ModerationRefused::class)]
final class ModerationTest extends TestCase
{
    use Refusals;

    public function testAMemberReportsAContentOnceWhileItIsOpen(): void
    {
        $stage = new Stage();
        $stage->people->signIn('m-1');

        $target = TargetReference::of('forum.post', '1');

        $report = $stage->reports->report($target, 'spam', '  Ads  ');
        $again = $stage->reports->report($target, 'hate');

        self::assertSame($report->id, $again->id);
        self::assertSame('m-1', $report->reporter?->value);
        self::assertSame(ReportSource::Member, $report->source);
        self::assertSame('Ads', $report->comment);
        self::assertCount(1, $stage->reportRepository->openOn($target));
        self::assertCount(1, $stage->events->of(ReportFiled::class));
        self::assertSame($report, $stage->events->of(ReportFiled::class)[0]->report);

        $stage->people->signIn('m-2');
        $stage->reports->report($target, 'hate');

        self::assertCount(2, $stage->reportRepository->openOn($target));
    }

    public function testWithoutADispatcherEveryChangeIsMadeAllTheSame(): void
    {
        $stage = new Stage(dispatching: false);
        $stage->posts->contents['1'] = new TargetView('A post', author: MemberId::of('author'));
        $stage->comments->contents['7'] = new TargetView('A comment', author: MemberId::of('author'));
        $stage->people->signIn('m-1');
        $stage->people->trusted = ['m-1'];

        $stage->reports->report(TargetReference::of('forum.post', '1'), 'spam');
        $stage->hides->hide(TargetReference::of('comment', '7'));

        $held = $stage->submissions->submit(TargetReference::of('forum.post', '2'), MemberId::of('newcomer'), 'Hello');
        $stage->people->signIn('mod-1', $stage->moderator);
        $decision = $stage->decisions->decide(TargetReference::of('forum.post', '1'), DecisionAction::Hide, 'Advertising');

        self::assertSame(ScreeningVerdict::Hold, $held->verdict);
        self::assertSame(DecisionAction::Hide, $decision->action);
        self::assertSame([], $stage->events->events);
    }

    public function testAReportIsRefused(): void
    {
        $stage = new Stage();
        $target = TargetReference::of('forum.post', '1');

        self::assertRefused(ModerationRefused::class, 'Only a signed-in member reports a content.', static fn (): mixed => $stage->reports->report($target, 'spam'));

        $stage->people->signIn('m-1');

        self::assertRefused(ModerationRefused::class, '"first-post" is not a reason of the catalogue.', static fn (): mixed => $stage->reports->report($target, 'first-post'));
        self::assertRefused(UnknownTarget::class, 'No moderation target is registered for the type "listing".', static fn (): mixed => $stage->reports->report(TargetReference::of('listing', '1'), 'spam'));
        self::assertSame([], $stage->events->events);
    }

    public function testAModeratorDecidesAndTheReportsClose(): void
    {
        $stage = new Stage();
        $target = TargetReference::of('forum.post', '1');
        $stage->posts->contents['1'] = new TargetView('A post', author: MemberId::of('author'));
        $stage->people->signIn('m-1');
        $report = $stage->reports->report($target, 'spam');
        $stage->clock->sleep(60);
        $stage->people->signIn('mod-1', $stage->moderator);

        $decision = $stage->decisions->decide($target, DecisionAction::Hide, 'Advertising', 'Second time', public: true);

        self::assertSame([['1', DecisionAction::Hide]], $stage->posts->applied);
        self::assertSame('mod-1', $decision->moderator?->value);
        self::assertSame('moderator', $decision->role);
        self::assertSame('author', $decision->author?->value);
        self::assertSame('Advertising', $decision->publicReason);
        self::assertSame('Second time', $decision->note);
        self::assertSame('2026-11-13 10:01:00', $decision->decidedAt->format('Y-m-d H:i:s'));
        self::assertSame([], $stage->reportRepository->openOn($target));
        self::assertSame($decision->id, $stage->reportRepository->madeBy(MemberId::of('m-1'))[0]->decisionId);
        self::assertSame($report->id, $stage->reportRepository->madeBy(MemberId::of('m-1'))[0]->id);
        self::assertSame([$decision->id], array_map(static fn (Decision $d): string => $d->id, $stage->decisions->on($target)));
        self::assertSame([$decision->id], array_map(static fn (Decision $d): string => $d->id, $stage->decisions->about(MemberId::of('author'))));
        self::assertSame($decision, $stage->events->of(DecisionTaken::class)[0]->decision);
        self::assertNull($stage->sandboxRepository->clearedAt(MemberId::of('author')));
    }

    public function testADismissalLeavesTheContentAsItIs(): void
    {
        $stage = new Stage();
        $stage->people->signIn('mod-1', $stage->moderator);

        $decision = $stage->decisions->decide(TargetReference::of('forum.post', 'gone'), DecisionAction::Dismiss, public: false);

        self::assertSame([], $stage->posts->applied);
        self::assertNull($decision->author);
        self::assertFalse($decision->public);
        self::assertNull($decision->publicReason);
    }

    public function testADecisionIsRefused(): void
    {
        $stage = new Stage();
        $target = TargetReference::of('forum.post', '1');

        self::assertFalse($stage->decisions->mayDecide('forum.post'));
        self::assertRefused(ModerationRefused::class, 'Only a moderator decides.', static fn (): mixed => $stage->decisions->decide($target, DecisionAction::Hide));

        $stage->people->signIn('m-1');
        self::assertRefused(ModerationRefused::class, 'Only a moderator decides.', static fn (): mixed => $stage->decisions->decide($target, DecisionAction::Hide));

        $stage->people->signIn('mod-2', $stage->commentsOnly);
        self::assertFalse($stage->decisions->mayDecide('forum.post'));
        self::assertTrue($stage->decisions->mayDecide('comment'));
        self::assertRefused(ModerationRefused::class, 'The role "comment-keeper" does not decide on "forum.post".', static fn (): mixed => $stage->decisions->decide($target, DecisionAction::Hide));

        $stage->people->signIn('mod-1', $stage->moderator);
        self::assertRefused(UnknownTarget::class, 'listing', static fn (): mixed => $stage->decisions->decide(TargetReference::of('listing', '1'), DecisionAction::Hide));
        self::assertSame([], $stage->posts->applied);
        self::assertSame([], $stage->events->events);
    }

    public function testTheQueueIsWhatTheRoleCovers(): void
    {
        $stage = new Stage();
        $stage->posts->contents['1'] = new TargetView('A post');
        $stage->people->signIn('m-1');
        $stage->reports->report(TargetReference::of('forum.post', '1'), 'spam');
        $stage->clock->sleep(60);
        $stage->reports->report(TargetReference::of('comment', '9'), 'hate');
        $stage->people->signIn('m-2');
        $stage->reports->report(TargetReference::of('forum.post', '1'), 'hate');

        self::assertSame([], $stage->queue->items(10));
        self::assertSame(0, $stage->queue->count());

        $stage->people->signIn('mod-1', $stage->moderator);
        $items = $stage->queue->items(10);

        self::assertSame(2, $stage->queue->count());
        self::assertSame(['forum.post', 'comment'], array_map(static fn (QueueItem $item): string => $item->target->type, $items));
        self::assertSame(2, $items[0]->reports);
        self::assertSame(['spam', 'hate'], $items[0]->reasons);
        self::assertSame([ReportSource::Member], $items[0]->sources);
        self::assertSame(['forum.post'], array_map(static fn (QueueItem $item): string => $item->target->type, $stage->queue->items(1)));
        self::assertSame(['comment'], array_map(static fn (QueueItem $item): string => $item->target->type, $stage->queue->items(1, 1)));

        $stage->people->signIn('mod-2', $stage->commentsOnly);

        self::assertSame(['comment'], array_map(static fn (QueueItem $item): string => $item->target->type, $stage->queue->items(10)));
        self::assertSame(1, $stage->queue->count());

        $entry = $stage->queue->entry(TargetReference::of('forum.post', '1'));

        self::assertSame('A post', $entry->view?->title);
        self::assertCount(2, $entry->reports);
        self::assertSame([], $entry->decisions);
        self::assertNull($stage->queue->entry(TargetReference::of('listing', '1'))->view);
        self::assertNull($stage->queue->describe(TargetReference::of('listing', '1')));
        self::assertSame('A post', $stage->queue->describe(TargetReference::of('forum.post', '1'))?->title);
    }

    public function testTheFirstPostWaitsForTheModeration(): void
    {
        $stage = new Stage();
        $author = MemberId::of('newcomer');
        $first = TargetReference::of('forum.post', '1');
        $stage->posts->contents['1'] = new TargetView('Hello', author: $author);

        $submission = $stage->submissions->submit($first, $author, 'Hello everyone');

        self::assertSame(ScreeningVerdict::Hold, $submission->verdict);
        self::assertSame('Hello everyone', $submission->text);
        $held = $stage->reportRepository->openOn($first);
        self::assertSame(ReportSource::Sandbox, $held[0]->source);
        self::assertSame(Reasons::FIRST_POST, $held[0]->reason);
        self::assertNull($held[0]->reporter);
        self::assertNull($held[0]->comment);
        self::assertSame($held[0]->id, $stage->events->of(ContentHeld::class)[0]->report->id);
        self::assertSame(ScreeningVerdict::Allow, $stage->submissions->submit(TargetReference::of('comment', '5'), $author, 'A comment')->verdict);

        $stage->people->signIn('mod-1', $stage->moderator);
        $stage->decisions->decide($first, DecisionAction::Approve);

        self::assertSame([['1', DecisionAction::Approve]], $stage->posts->applied);
        self::assertNotNull($stage->sandboxRepository->clearedAt($author));
        self::assertSame(ScreeningVerdict::Allow, $stage->submissions->submit(TargetReference::of('forum.post', '2'), $author)->verdict);
        self::assertNull($stage->submissions->submit(TargetReference::of('forum.post', '3'), $author)->text);
    }

    public function testApprovingWithoutASandboxReportLeavesTheAuthorInIt(): void
    {
        $stage = new Stage();
        $author = MemberId::of('newcomer');
        $stage->posts->contents['1'] = new TargetView('Hello', author: $author);
        $stage->people->signIn('mod-1', $stage->moderator);

        $stage->decisions->decide(TargetReference::of('forum.post', '1'), DecisionAction::Approve);
        self::assertNull($stage->sandboxRepository->clearedAt($author));

        $stage->submissions->submit(TargetReference::of('forum.post', '1'), $author);
        $stage->decisions->decide(TargetReference::of('forum.post', '1'), DecisionAction::Remove);
        self::assertNull($stage->sandboxRepository->clearedAt($author));
    }

    public function testTheWatchedWords(): void
    {
        $stage = new Stage(new ScriptedWords(['casino' => ScreeningVerdict::Hold, 'darn' => ScreeningVerdict::Flag, 'slur' => ScreeningVerdict::Block]), []);
        $author = MemberId::of('m-1');

        $blocked = $stage->submissions->submit(TargetReference::of('comment', '1'), $author, 'a slur here');
        self::assertSame(ScreeningVerdict::Block, $blocked->verdict);
        self::assertSame('a **** here', $blocked->text);
        self::assertSame([], $stage->reportRepository->openOn(TargetReference::of('comment', '1')));

        $held = $stage->submissions->submit(TargetReference::of('comment', '2'), $author, 'casino night');
        self::assertSame(ScreeningVerdict::Hold, $held->verdict);
        $report = $stage->reportRepository->openOn(TargetReference::of('comment', '2'))[0];
        self::assertSame(ReportSource::WatchedWords, $report->source);
        self::assertSame(Reasons::WATCHED_WORDS, $report->reason);
        self::assertSame('casino', $report->comment);
        self::assertCount(1, $stage->events->of(ContentHeld::class));

        $flagged = $stage->submissions->submit(TargetReference::of('comment', '3'), $author, 'darn it');
        self::assertSame(ScreeningVerdict::Flag, $flagged->verdict);
        self::assertSame('**** it', $flagged->text);
        self::assertSame(ReportSource::WatchedWords, $stage->reportRepository->openOn(TargetReference::of('comment', '3'))[0]->source);
        self::assertCount(1, $stage->events->of(ContentHeld::class));

        $both = $stage->submissions->submit(TargetReference::of('comment', '4'), $author, 'casino and darn');
        self::assertSame('casino, darn', $stage->reportRepository->openOn(TargetReference::of('comment', '4'))[0]->comment);
        self::assertSame(ScreeningVerdict::Flag, $both->verdict);
    }

    public function testAFlaggedFirstPostIsHeld(): void
    {
        $stage = new Stage(new ScriptedWords(['darn' => ScreeningVerdict::Flag]));
        $submission = $stage->submissions->submit(TargetReference::of('forum.post', '1'), MemberId::of('m-1'), 'darn');

        self::assertSame(ScreeningVerdict::Hold, $submission->verdict);
        self::assertSame(ReportSource::Sandbox, $stage->reportRepository->openOn(TargetReference::of('forum.post', '1'))[0]->source);
        self::assertSame('darn', $stage->reportRepository->openOn(TargetReference::of('forum.post', '1'))[0]->comment);
    }

    public function testATrustedMemberHidesUntilTheModerationReviews(): void
    {
        $stage = new Stage();
        $target = TargetReference::of('comment', '7');

        self::assertFalse($stage->hides->mayHide());
        self::assertRefused(ModerationRefused::class, 'Only a trusted member hides a content.', static fn (): mixed => $stage->hides->hide($target));

        $stage->people->signIn('m-1');
        self::assertFalse($stage->hides->mayHide());
        self::assertRefused(ModerationRefused::class, 'Only a trusted member hides a content.', static fn (): mixed => $stage->hides->hide($target));

        $stage->people->trusted = ['m-1'];
        self::assertTrue($stage->hides->mayHide());
        $report = $stage->hides->hide($target, 'Doxxing');

        self::assertSame([['7', DecisionAction::Hide]], $stage->comments->applied);
        self::assertSame(ReportSource::TrustedHide, $report->source);
        self::assertSame(Reasons::HIDDEN_BY_MEMBER, $report->reason);
        self::assertSame('m-1', $report->reporter?->value);
        self::assertSame('Doxxing', $report->comment);
        self::assertSame($report, $stage->events->of(ContentHiddenByMember::class)[0]->report);

        $stage->people->signIn('mod-1', $stage->moderator);
        $stage->decisions->decide($target, DecisionAction::Restore);

        self::assertSame([['7', DecisionAction::Hide], ['7', DecisionAction::Restore]], $stage->comments->applied);
    }

    public function testTheNotesOnAMember(): void
    {
        $stage = new Stage();
        $member = MemberId::of('m-2');

        self::assertRefused(ModerationRefused::class, 'Only a moderator reads and writes the notes on a member.', static fn (): mixed => $stage->notes->write($member, 'x'));
        self::assertRefused(ModerationRefused::class, 'Only a moderator', static fn (): mixed => $stage->notes->about($member));
        self::assertRefused(ModerationRefused::class, 'Only a moderator', static fn (): mixed => $stage->notes->remove(str_repeat('a', 32)));

        $stage->people->signIn('mod-1', $stage->moderator);
        $first = $stage->notes->write($member, 'Warned once');
        $stage->clock->sleep(60);
        $second = $stage->notes->write($member, 'Warned twice');

        self::assertSame('mod-1', $first->author?->value);
        self::assertSame([$second->id, $first->id], array_map(static fn (MemberNote $note): string => $note->id, $stage->notes->about($member)));
        self::assertTrue($stage->notes->remove($first->id));
        self::assertFalse($stage->notes->remove($first->id));
        self::assertCount(1, $stage->notes->about($member));
    }

    public function testThePublicJournalShowsTheRoleAndNeverTheName(): void
    {
        $stage = new Stage();
        $stage->people->signIn('mod-1', $stage->moderator);
        $stage->decisions->decide(TargetReference::of('forum.post', '1'), DecisionAction::Remove, 'Spam');
        $stage->clock->sleep(60);
        $stage->decisions->decide(TargetReference::of('comment', '2'), DecisionAction::Warn, public: false);
        $stage->clock->sleep(60);
        $stage->decisions->decide(TargetReference::of('comment', '3'), DecisionAction::Hide, 'Off topic');

        $entries = $stage->journal->entries(10);

        self::assertSame(2, $stage->journal->count());
        self::assertCount(2, $entries);
        self::assertSame(DecisionAction::Hide, $entries[0]->action);
        self::assertSame('comment', $entries[0]->targetType);
        self::assertSame('moderator', $entries[0]->role);
        self::assertSame('Off topic', $entries[0]->reason);
        self::assertSame('2026-11-13 10:02:00', $entries[0]->decidedAt->format('Y-m-d H:i:s'));
        self::assertSame(DecisionAction::Remove, $entries[1]->action);
        self::assertSame(DecisionAction::Remove, $stage->journal->entries(1, 1)[0]->action);
        self::assertStringNotContainsString('mod-1', serialize($entries));
    }

    public function testASurgeOfReports(): void
    {
        $stage = new Stage();
        $stage->people->signIn('m-1');

        foreach (['1', '2'] as $id) {
            $stage->reports->report(TargetReference::of('forum.post', $id), 'spam');
        }

        self::assertSame(2, $stage->surge->count());
        self::assertFalse($stage->surge->isSurging());

        $stage->reports->report(TargetReference::of('forum.post', '3'), 'spam');

        self::assertTrue($stage->surge->isSurging());
        self::assertSame(3, $stage->surge->threshold());
        self::assertSame(3600, $stage->surge->window());

        $stage->clock->sleep(3600);
        self::assertSame(3, $stage->surge->count());
        $stage->clock->sleep(1);
        self::assertSame(0, $stage->surge->count());
    }
}
