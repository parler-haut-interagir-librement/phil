<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Unit\Application;

use Phil\ModerationBundle\Application\Decisions;
use Phil\ModerationBundle\Application\Event\DecisionTaken;
use Phil\ModerationBundle\Application\Event\ReportFiled;
use Phil\ModerationBundle\Application\Event\StatementIssued;
use Phil\ModerationBundle\Application\Grounds;
use Phil\ModerationBundle\Application\ReasonGrounds;
use Phil\ModerationBundle\Application\Redress;
use Phil\ModerationBundle\Application\ScreeningVerdict;
use Phil\ModerationBundle\Application\StatementsOfReasons;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\Status\Ground;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\StatementOfReasons;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Phil\ModerationBundle\Tests\Fixture\Refusals;
use Phil\ModerationBundle\Tests\Fixture\ScriptedWords;
use Phil\ModerationBundle\Tests\Fixture\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(StatementsOfReasons::class)]
#[CoversClass(Decisions::class)]
#[CoversClass(Grounds::class)]
#[CoversClass(ReasonGrounds::class)]
#[CoversClass(Redress::class)]
#[CoversClass(StatementIssued::class)]
final class StatementsTest extends TestCase
{
    use Refusals;

    public function testARestrictionComesWithItsStatement(): void
    {
        $stage = $this->stage();
        $target = TargetReference::of('forum.post', '1');
        $stage->people->signIn('m-1');
        $stage->reports->report($target, 'spam');
        $stage->clock->sleep(60);
        $stage->people->signIn('mod-1', $stage->moderator);

        $decision = $stage->reasoned->decide($target, DecisionAction::Hide, 'Advertising', grounds: new Grounds('spam', '  The post advertises a shop.  '));
        $statement = $stage->statementRepository->forDecision($decision->id);

        self::assertInstanceOf(StatementOfReasons::class, $statement);
        self::assertSame($decision->id, $statement->decisionId);
        self::assertSame('author', $statement->recipient->value);
        self::assertEquals($target, $statement->target);
        self::assertSame(DecisionAction::Hide, $statement->action);
        self::assertSame('spam', $statement->reason);
        self::assertSame(Ground::Terms, $statement->ground);
        self::assertSame('Terms, section 4', $statement->reference);
        self::assertSame('The post advertises a shop.', $statement->explanation);
        self::assertTrue($statement->prompted);
        self::assertFalse($statement->automatedDetection);
        self::assertSame('2026-11-13 10:01:00', $statement->issuedAt->format('Y-m-d H:i:s'));
        self::assertSame([['1', DecisionAction::Hide]], $stage->posts->applied);
        self::assertSame(
            [ReportFiled::class, DecisionTaken::class, StatementIssued::class],
            array_map(static fn (object $event): string => $event::class, $stage->events->events),
        );
        self::assertSame($statement->id, $stage->events->of(StatementIssued::class)[0]->statement->id);
    }

    public function testTheModeratorNamesAnotherProvision(): void
    {
        $stage = $this->stage();
        $stage->people->signIn('mod-1', $stage->moderator);

        $named = $stage->reasoned->decide(TargetReference::of('forum.post', '1'), DecisionAction::Remove, grounds: new Grounds('spam', 'Ads', '  Rule 2  '));
        $blank = $stage->reasoned->decide(TargetReference::of('forum.post', '1'), DecisionAction::Warn, grounds: new Grounds('spam', 'Ads', '   '));
        $none = $stage->reasoned->decide(TargetReference::of('forum.post', '1'), DecisionAction::Warn, grounds: new Grounds('harassment', 'Insults'));

        self::assertSame('Rule 2', $stage->statementRepository->forDecision($named->id)?->reference);
        self::assertSame('Terms, section 4', $stage->statementRepository->forDecision($blank->id)?->reference);
        self::assertNull($stage->statementRepository->forDecision($none->id)?->reference);
        self::assertFalse($stage->statementRepository->forDecision($none->id)?->prompted);
        self::assertSame([['1', DecisionAction::Remove]], $stage->posts->applied);
    }

    public function testIllegalContentRestsOnTheLawItNames(): void
    {
        $stage = $this->stage();
        $stage->people->signIn('mod-1', $stage->moderator);

        $target = TargetReference::of('forum.post', '1');

        $decision = $stage->reasoned->decide($target, DecisionAction::Remove, grounds: new Grounds('illegal', 'Counterfeit goods offered for sale.', 'Trademarks Act, s. 19'));

        self::assertSame(Ground::Illegal, $stage->statementRepository->forDecision($decision->id)?->ground);
        self::assertSame('Trademarks Act, s. 19', $stage->statementRepository->forDecision($decision->id)?->reference);

        self::assertRefused(InvalidModeration::class, 'A restriction for illegal content names the law it rests on.', static fn (): mixed => $stage->reasoned->decide($target, DecisionAction::Hide, grounds: new Grounds('illegal', 'Counterfeit goods.')));
        self::assertCount(1, $stage->decisions->on($target));
    }

    public function testARestrictionWithoutItsGroundsChangesNothing(): void
    {
        $stage = $this->stage();
        $target = TargetReference::of('forum.post', '1');
        $stage->people->signIn('m-1');
        $stage->reports->report($target, 'spam');
        $stage->people->signIn('mod-1', $stage->moderator);
        $stage->events->events = [];

        self::assertRefused(InvalidModeration::class, 'A decision that restricts the author states its grounds: a reason and the facts it rests on.', static fn (): mixed => $stage->reasoned->decide($target, DecisionAction::Hide));
        self::assertRefused(InvalidModeration::class, '"first-post" is not a reason of the catalogue.', static fn (): mixed => $stage->reasoned->decide($target, DecisionAction::Remove, grounds: new Grounds('first-post', 'Facts')));
        self::assertRefused(InvalidModeration::class, 'A statement of reasons explains the facts the decision rests on.', static fn (): mixed => $stage->reasoned->decide($target, DecisionAction::Warn, grounds: new Grounds('spam', '   ')));
        self::assertRefused(InvalidModeration::class, 'An explanation has at most 2000 characters.', static fn (): mixed => $stage->reasoned->decide($target, DecisionAction::Warn, grounds: new Grounds('spam', str_repeat('a', 2001))));

        self::assertSame([], $stage->posts->applied);
        self::assertSame([], $stage->decisions->on($target));
        self::assertCount(1, $stage->reportRepository->openOn($target));
        self::assertSame([], $stage->events->events);
    }

    public function testNoStatementWhenNobodyIsRestricted(): void
    {
        $stage = $this->stage();
        $stage->people->signIn('mod-1', $stage->moderator);

        $grounds = new Grounds('spam', 'Ads');

        foreach ([DecisionAction::Dismiss, DecisionAction::Approve, DecisionAction::Restore] as $action) {
            $decision = $stage->reasoned->decide(TargetReference::of('forum.post', '1'), $action, grounds: $grounds);
            self::assertNull($stage->statementRepository->forDecision($decision->id), $action->value);
        }

        $orphan = $stage->reasoned->decide(TargetReference::of('forum.post', 'gone'), DecisionAction::Remove);

        self::assertNull($orphan->author);
        self::assertNull($stage->statementRepository->forDecision($orphan->id));
        self::assertSame([], $stage->events->of(StatementIssued::class));
    }

    public function testWatchedWordsTellOfAnAutomatedDetection(): void
    {
        $stage = $this->stage(new ScriptedWords(['casino' => ScreeningVerdict::Hold]));
        $target = TargetReference::of('comment', '7');
        $stage->comments->contents['7'] = new TargetView('A comment', author: MemberId::of('author'));
        $stage->submissions->submit($target, MemberId::of('author'), 'casino night');
        $stage->people->signIn('mod-1', $stage->moderator);

        $decision = $stage->reasoned->decide($target, DecisionAction::Remove, grounds: new Grounds('spam', 'A casino advertisement.'));
        $statement = $stage->statementRepository->forDecision($decision->id);

        self::assertTrue($statement?->automatedDetection);
        self::assertFalse($statement->prompted);
    }

    public function testOnlyTheMemberItIsAddressedToReadsIt(): void
    {
        $stage = $this->stage();
        $stage->people->signIn('mod-1', $stage->moderator);

        $first = $stage->reasoned->decide(TargetReference::of('forum.post', '1'), DecisionAction::Warn, grounds: new Grounds('spam', 'First'));
        $stage->clock->sleep(60);
        $second = $stage->reasoned->decide(TargetReference::of('forum.post', '1'), DecisionAction::Hide, grounds: new Grounds('spam', 'Second'));
        $firstId = $stage->statementRepository->forDecision($first->id)->id ?? '';
        $secondId = $stage->statementRepository->forDecision($second->id)->id ?? '';

        self::assertSame($firstId, $stage->statements->forDecision($first->id)?->id);
        self::assertNull($stage->statements->mine($firstId));
        self::assertSame([], $stage->statements->allMine(10));
        self::assertSame(0, $stage->statements->countMine());

        $stage->people->signIn('author');

        self::assertSame($firstId, $stage->statements->mine($firstId)?->id);
        self::assertNull($stage->statements->mine(str_repeat('0', 32)));
        self::assertSame([$secondId, $firstId], array_map(static fn (StatementOfReasons $s): string => $s->id, $stage->statements->allMine(10)));
        self::assertSame([$firstId], array_map(static fn (StatementOfReasons $s): string => $s->id, $stage->statements->allMine(1, 1)));
        self::assertSame(2, $stage->statements->countMine());

        $stage->people->signOut();

        self::assertNull($stage->statements->mine($firstId));
        self::assertSame([], $stage->statements->allMine(10));
        self::assertSame(0, $stage->statements->countMine());
        self::assertSame('/terms', $stage->statements->redress()->termsUrl);
        self::assertSame('/contact', $stage->statements->redress()->complaintUrl);
        self::assertSame([['name' => 'A settlement body', 'url' => 'https://settlement.example']], $stage->statements->redress()->outOfCourt);
    }

    public function testTheGroundOfEachReason(): void
    {
        $default = new ReasonGrounds();

        self::assertSame(Ground::Illegal, $default->ground('illegal'));
        self::assertSame(Ground::Terms, $default->ground('spam'));
        self::assertNull($default->reference('illegal'));

        $configured = new ReasonGrounds([
            'illegal' => ['ground' => 'terms'],
            'hate' => ['ground' => 'illegal', 'reference' => '  Criminal Code, s. 319  '],
            'spam' => ['ground' => 'terms', 'reference' => '   '],
        ]);

        self::assertSame(Ground::Terms, $configured->ground('illegal'));
        self::assertSame(Ground::Illegal, $configured->ground('hate'));
        self::assertSame('Criminal Code, s. 319', $configured->reference('hate'));
        self::assertNull($configured->reference('spam'));
        self::assertNull($configured->reference('illegal'));

        self::assertRefused(InvalidModeration::class, '"Spam" is not a reason', static fn (): ReasonGrounds => new ReasonGrounds(['Spam' => ['ground' => 'terms']]));
        self::assertRefused(InvalidModeration::class, 'A reference has at most 255 characters.', static fn (): ReasonGrounds => new ReasonGrounds(['spam' => ['ground' => 'terms', 'reference' => str_repeat('a', 256)]]));
        self::assertRefused(ValueError::class, 'law', static fn (): ReasonGrounds => new ReasonGrounds(['spam' => ['ground' => 'law']]));
    }

    public function testTheRedressByDefault(): void
    {
        $redress = new Redress();

        self::assertNull($redress->termsUrl);
        self::assertNull($redress->complaintUrl);
        self::assertSame([], $redress->outOfCourt);
    }

    public function testAStatementIsKeptWithoutAnyDispatcher(): void
    {
        $stage = new Stage(null, [], null, false);
        $stage->posts->contents['1'] = new TargetView('A post', author: MemberId::of('author'));
        $stage->people->signIn('mod-1', $stage->moderator);

        $decision = $stage->reasoned->decide(TargetReference::of('forum.post', '1'), DecisionAction::Hide, grounds: new Grounds('spam', 'Facts'));

        self::assertInstanceOf(StatementOfReasons::class, $stage->statementRepository->forDecision($decision->id));
    }

    private function stage(?ScriptedWords $words = null): Stage
    {
        $stage = new Stage($words, []);
        $stage->posts->contents['1'] = new TargetView('A post', author: MemberId::of('author'));

        return $stage;
    }
}
