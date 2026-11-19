<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Application;

use Override;
use Phil\ReactionsBundle\Application\ConfiguredVisibility;
use Phil\ReactionsBundle\Application\DenyingReactionAccess;
use Phil\ReactionsBundle\Application\NoCurrentMember;
use Phil\ReactionsBundle\Application\NoMemberNames;
use Phil\ReactionsBundle\Application\ReactionBoard;
use Phil\ReactionsBundle\Application\ReactionSubject;
use Phil\ReactionsBundle\Application\View\PickerGroup;
use Phil\ReactionsBundle\Application\View\ReactionItem;
use Phil\ReactionsBundle\Application\View\ReactionPanel;
use Phil\ReactionsBundle\Application\View\ReactorsLine;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Status\Audience;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionVisibility;
use Phil\ReactionsBundle\Tests\Fixtures\Stage;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(ReactionBoard::class)]
#[CoversClass(ReactionPanel::class)]
#[CoversClass(ReactionItem::class)]
#[CoversClass(PickerGroup::class)]
#[CoversClass(ReactorsLine::class)]
#[CoversClass(ReactionVisibility::class)]
#[CoversClass(ConfiguredVisibility::class)]
#[CoversClass(DenyingReactionAccess::class)]
#[CoversClass(NoCurrentMember::class)]
#[CoversClass(NoMemberNames::class)]
final class ReactionBoardTest extends TestCase
{
    private Stage $stage;

    /** Gives the reactions identifiers in the order they are made, as UUID v7 do. */
    private int $sequence = 0;

    private ResourceReference $article;

    #[Override]
    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->article = ResourceReference::of('article', '1');
    }

    public function testAMemberSeesTheQuickReactionsAndThePicker(): void
    {
        $panel = $this->stage->board->panel($this->article);

        self::assertTrue($panel->visible);
        self::assertSame(['like', 'thanks', 'not_useful'], $this->codes($panel->bar));
        self::assertSame(['source.unicode', 'source.site'], array_map(static fn (PickerGroup $group): string => $group->label, $panel->picker));
        self::assertSame(['spark'], $this->codes($panel->picker[0]->items));
        self::assertSame(['gnome'], $this->codes($panel->picker[1]->items));

        $like = $panel->bar[0];
        self::assertSame(['label.like', '👍', null, null, 0, 'reactions.count', false, true], [$like->label, $like->emoji, $like->image, $like->imageAlt, $like->count, $like->countLabel, $like->pressed, $like->interactive]);
        $gnome = $panel->picker[1]->items[0];
        self::assertSame(['label.gnome', null, '/e/gnome.webp', 'alt.gnome'], [$gnome->label, $gnome->emoji, $gnome->image, $gnome->imageAlt]);
    }

    public function testSeveralReactionsOfAMemberArePressedUntilTheCap(): void
    {
        $this->stage = new Stage(3);
        $this->react('ada', 'like');
        $this->react('ada', 'spark');
        $this->react('linus', 'thanks');

        $panel = $this->stage->board->panel($this->article);
        self::assertFalse($panel->full);
        self::assertSame(['like', 'thanks', 'not_useful', 'spark'], $this->codes($panel->bar));
        self::assertSame([true, false, false, true], array_map(static fn (ReactionItem $item): bool => $item->pressed, $panel->bar));
        self::assertSame(['gnome'], $this->codes($panel->picker[0]->items));

        $this->react('ada', 'gnome');
        $this->stage->board->reset();
        $panel = $this->stage->board->panel($this->article);

        self::assertTrue($panel->full);
        self::assertSame(['like', 'thanks', 'spark', 'gnome'], $this->codes($panel->bar));
        self::assertSame([[true, true], [false, false], [true, true], [true, true]], array_map(static fn (ReactionItem $item): array => [$item->pressed, $item->interactive], $panel->bar));
        self::assertSame([], $panel->picker);
    }

    public function testOneReactionPerMemberIsNeverFull(): void
    {
        $this->react('ada', 'like');

        $panel = $this->stage->board->panel($this->article);

        self::assertFalse($panel->full);
        self::assertSame([true, true, true], array_map(static fn (ReactionItem $item): bool => $item->interactive, $panel->bar));
        self::assertNotSame([], $panel->picker);
    }

    public function testACountedReactionComesToTheBarAndThePressedOneIsPressed(): void
    {
        $this->react('ada', 'spark');
        $this->react('linus', 'gnome');
        $this->stage->board->reset();

        $panel = $this->stage->board->panel($this->article);

        self::assertSame(['like', 'thanks', 'not_useful', 'spark', 'gnome'], $this->codes($panel->bar));
        self::assertSame([true, 1], [$panel->bar[3]->pressed, $panel->bar[3]->count]);
        self::assertFalse($panel->bar[4]->pressed);
        self::assertSame([], $panel->picker);
    }

    public function testAVisitorSeesOnlyWhatIsCountedAndPressesNothing(): void
    {
        $this->react('linus', 'like');
        $this->react('grace', 'spark');
        $this->stage->member = null;
        $this->stage->asked = [];

        $panel = $this->stage->board->panel($this->article);

        self::assertSame(['like', 'spark'], $this->codes($panel->bar));
        self::assertSame([false, false], [$panel->bar[0]->interactive, $panel->bar[1]->interactive]);
        self::assertSame([], $panel->picker);
        self::assertSame([], $this->stage->asked);
    }

    public function testAPrivateReactionHasNoCounterAndIsSeenPressedByItsMemberOnly(): void
    {
        $this->react('ada', 'not_useful');
        $this->react('linus', 'not_useful');
        $this->stage->names = ['ada' => 'Ada', 'linus' => 'Linus'];
        $this->stage->board->reset();

        $private = $this->stage->board->panel($this->article);
        self::assertSame(['like', 'thanks', 'not_useful'], $this->codes($private->bar));
        self::assertSame([null, null, true], [$private->bar[2]->count, $private->bar[2]->countLabel, $private->bar[2]->pressed]);
        self::assertSame([], $private->reactors);

        $this->stage->member = null;
        $this->stage->board->reset();
        self::assertSame([], $this->stage->board->panel($this->article)->bar);
    }

    public function testThePanelForMembersOnly(): void
    {
        $this->stage->visibility = new ReactionVisibility(Audience::Members, Audience::Everyone, Audience::Everyone);

        self::assertTrue($this->stage->board->panel($this->article)->visible);

        $this->stage->member = null;
        $hidden = $this->stage->board->panel($this->article);
        self::assertFalse($hidden->visible);
        self::assertSame([[], [], []], [$hidden->bar, $hidden->picker, $hidden->reactors]);
    }

    public function testCountersForMembersOnly(): void
    {
        $this->react('linus', 'like');
        $this->stage->visibility = new ReactionVisibility(Audience::Everyone, Audience::Members, Audience::Nobody);

        self::assertSame(1, $this->stage->board->panel($this->article)->bar[0]->count);

        $this->stage->member = null;
        $this->stage->board->reset();
        self::assertSame([], $this->stage->board->panel($this->article)->bar);
    }

    public function testNoCounterMakesAReactionStayWhereItIs(): void
    {
        $this->react('linus', 'spark');
        $this->stage->visibility = new ReactionVisibility(Audience::Everyone, Audience::Nobody, Audience::Nobody);

        $panel = $this->stage->board->panel($this->article);

        self::assertSame(['like', 'thanks', 'not_useful'], $this->codes($panel->bar));
        self::assertNull($panel->bar[0]->count);
        self::assertSame(['spark'], $this->codes($panel->picker[0]->items));
    }

    public function testAReactionTheAccessRefuses(): void
    {
        $this->react('ada', 'thanks');
        $this->stage->board->reset();
        $this->stage->refused = ['like', 'spark', 'thanks'];

        $panel = $this->stage->board->panel($this->article);

        self::assertSame(['thanks', 'not_useful'], $this->codes($panel->bar));
        self::assertTrue($panel->bar[0]->interactive);
        self::assertSame(['gnome'], $this->codes($panel->picker[0]->items));
    }

    public function testACountedReactionTheAccessRefusesIsShownButNotPressable(): void
    {
        $this->react('linus', 'like');
        $this->stage->board->reset();
        $this->stage->refused = ['like'];

        $item = $this->stage->board->panel($this->article)->bar[0];

        self::assertSame(['like', false, 1], [$item->code, $item->interactive, $item->count]);
    }

    public function testTheVoterIsAskedAboutTheContentTheMemberAndTheReaction(): void
    {
        $this->stage->board->panel($this->article);

        $subject = $this->stage->asked[0];
        self::assertInstanceOf(ReactionSubject::class, $subject);
        self::assertSame(['article:1', 'ada', 'like'], [$subject->content->toString(), $subject->member, $subject->reaction->code]);
    }

    public function testTheNamesInTheOrderOfTheReactions(): void
    {
        $this->stage->names = ['linus' => 'Linus', 'ada' => 'Ada', 'grace' => 'Grace'];
        $this->react('ghost', 'like');
        $this->react('ada', 'like');
        $this->react('linus', 'like');
        $this->react('grace', 'spark');
        $this->stage->board->reset();

        $reactors = $this->stage->board->panel($this->article)->reactors;

        self::assertCount(2, $reactors);
        self::assertSame(['label.like', ['Ada', 'Linus'], 0], [$reactors[0]->label, $reactors[0]->names, $reactors[0]->others]);
        self::assertSame(['label.spark', ['Grace'], 0], [$reactors[1]->label, $reactors[1]->names, $reactors[1]->others]);
        self::assertSame([['ghost', 'ada', 'linus', 'grace']], $this->stage->namesAsked);
    }

    public function testTheOthersBeyondTheFirstNames(): void
    {
        $names = [];

        for ($index = 0; $index < ReactionBoard::NAMES_SHOWN + 3; ++$index) {
            $this->react('m'.$index, 'like');
            $names['m'.$index] = 'Name '.$index;
        }

        $this->stage->names = $names;
        $this->stage->board->reset();

        [$line] = $this->stage->board->panel($this->article)->reactors;
        self::assertCount(10, $line->names);
        self::assertSame(3, $line->others);
        self::assertSame([[['article:1'], 10]], $this->stage->repository->reactorsAsked);

        $this->stage->visibility = new ReactionVisibility(Audience::Everyone, Audience::Nobody, Audience::Everyone);
        $this->stage->board->reset();
        self::assertSame(0, $this->stage->board->panel($this->article)->reactors[0]->others);
    }

    public function testNamesAreReadOnlyForWhoSeesThem(): void
    {
        $this->react('linus', 'like');
        $this->stage->names = ['linus' => 'Linus'];
        $this->stage->visibility = new ReactionVisibility(Audience::Everyone, Audience::Everyone, Audience::Members);
        $this->stage->member = null;

        self::assertSame([], $this->stage->board->panel($this->article)->reactors);
        self::assertSame([], $this->stage->repository->reactorsAsked);

        $this->stage->member = 'ada';
        $this->stage->board->reset();
        self::assertCount(1, $this->stage->board->panel($this->article)->reactors);
    }

    public function testNobodyToNameAsksNoName(): void
    {
        $this->stage->board->panel($this->article);

        self::assertSame([], $this->stage->namesAsked);
    }

    public function testApageOfManyContentsIsReadInOneQueryForEachThing(): void
    {
        $this->react('ada', 'like');
        $other = ResourceReference::of('article', '2');
        $this->stage->board->reset();

        $this->stage->board->preload([$this->article, 'article:2', 'article:2']);
        $this->stage->board->panel($this->article);
        $this->stage->board->panel($other);

        self::assertSame([['article:1', 'article:2']], $this->stage->repository->counted);
        self::assertSame([['ada', ['article:1', 'article:2']]], $this->stage->repository->chosenAsked);
        self::assertSame([[['article:1', 'article:2'], 10]], $this->stage->repository->reactorsAsked);
    }

    public function testAPanelIsNotFullByDefault(): void
    {
        self::assertFalse(new ReactionPanel(true)->full);
    }

    public function testTheMembersAskedFormAListWhoeverRepeats(): void
    {
        $other = ResourceReference::of('article', '2');
        $now = $this->stage->clock->now();
        $this->react('ada', 'like');
        $this->react('linus', 'like');
        $this->stage->repository->add(Reaction::of('r-other-ada', $other, 'ada', 'like', $now));
        $this->stage->repository->add(Reaction::of('r-other-grace', $other, 'grace', 'like', $now));

        $this->stage->board->preload([$this->article, $other]);

        self::assertSame([['ada', 'grace', 'linus']], $this->stage->namesAsked);
    }

    public function testAMemberNamedTwiceIsAskedOnce(): void
    {
        $other = ResourceReference::of('article', '2');
        $this->react('ada', 'like');
        $this->stage->repository->add(Reaction::of('r-other-ada', $other, 'ada', 'like', $this->stage->clock->now()));
        $this->stage->repository->add(Reaction::of('r-other-linus', $other, 'linus', 'like', $this->stage->clock->now()));

        $this->stage->board->preload([$this->article, $other]);

        self::assertSame([['ada', 'linus']], $this->stage->namesAsked);
    }

    public function testWhatWasReadStaysThroughSeveralBatches(): void
    {
        $other = ResourceReference::of('article', '2');
        $this->stage->names = ['ada' => 'Ada'];
        $this->react('ada', 'like');
        $this->stage->repository->add(Reaction::of('r-other', $other, 'linus', 'thanks', $this->stage->clock->now()));

        $this->stage->board->preload([$this->article]);
        $this->stage->names = ['linus' => 'Linus'];
        $this->stage->board->preload([$other]);
        $this->stage->names = [];

        $panel = $this->stage->board->panel($this->article);

        self::assertCount(2, $this->stage->repository->counted);
        self::assertSame([1, true], [$panel->bar[0]->count, $panel->bar[0]->pressed]);
        self::assertSame(['Ada'], $panel->reactors[0]->names);
    }

    public function testTheCounterIsInItsLabel(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['reactions.count' => '%count% counted'], 'en', 'phil_reactions');
        $this->react('ada', 'like');
        $board = new ReactionBoard($this->stage->repository, $this->stage->catalog, $this->stage, $this->stage, $this->stage, $this->stage, $translator);

        self::assertSame('1 counted', $board->panel($this->article)->bar[0]->countLabel);
    }

    public function testAVisitorAsksNoChoice(): void
    {
        $this->stage->member = null;

        $this->stage->board->panel($this->article);

        self::assertSame([], $this->stage->repository->chosenAsked);
    }

    public function testVeryManyContentsAreCut(): void
    {
        $contents = [];

        for ($index = 0; $index < ReactionBoard::CHUNK + 1; ++$index) {
            $contents[] = 'article:'.$index;
        }

        $this->stage->board->preload($contents);

        self::assertSame([200, 1], array_map(\count(...), $this->stage->repository->counted));
        self::assertSame(200, ReactionBoard::CHUNK);
    }

    public function testAContentChangedIsReadAgain(): void
    {
        $other = ResourceReference::of('article', '2');
        $this->stage->board->preload([$this->article, $other]);
        $this->react('ada', 'like');
        self::assertSame(0, $this->stage->board->panel($this->article)->bar[0]->count);

        $this->stage->board->forget($this->article);
        $panel = $this->stage->board->panel($this->article);

        self::assertSame([1, true], [$panel->bar[0]->count, $panel->bar[0]->pressed]);
        self::assertCount(2, $this->stage->repository->counted);
        self::assertSame(['article:1'], $this->stage->repository->counted[1]);
    }

    public function testForgettingForgetsTheReactors(): void
    {
        $this->stage->names = ['ada' => 'Ada'];
        $this->stage->board->preload([$this->article]);
        $this->react('ada', 'like');

        $this->stage->board->forget($this->article);

        self::assertSame(['Ada'], $this->stage->board->panel($this->article)->reactors[0]->names);
    }

    public function testResetForgetsEverythingOfTheRequest(): void
    {
        $this->react('ada', 'like');
        $this->stage->names = ['ada' => 'Ada'];
        self::assertCount(1, $this->stage->board->panel($this->article)->reactors);

        $this->stage->reactions->withdraw($this->article, 'ada');
        $this->react('linus', 'like');
        $this->stage->names = [];
        $this->stage->board->reset();

        $panel = $this->stage->board->panel($this->article);

        self::assertSame([1, false], [$panel->bar[0]->count, $panel->bar[0]->pressed]);
        self::assertSame([], $panel->reactors);
    }

    public function testTheSafeDefaults(): void
    {
        $this->react('linus', 'like');
        self::assertSame([], new NoMemberNames()->names(['linus']));
        self::assertNull(new NoCurrentMember()->current());
        self::assertFalse(new DenyingReactionAccess()->canReact(new ReactionSubject($this->article, 'ada', ReactionDefinition::unicode('a', 'l', 'x'))));

        $board = new ReactionBoard($this->stage->repository, $this->stage->catalog, new DenyingReactionAccess(), new NoCurrentMember(), $this->stage, new NoMemberNames(), new IdentityTranslator());
        self::assertSame(['like'], $this->codes($board->panel($this->article)->bar));
    }

    public function testTheConfiguredVisibility(): void
    {
        $visibility = new ConfiguredVisibility('members', 'nobody', 'everyone')->of($this->article);

        self::assertSame([Audience::Members, Audience::Nobody, Audience::Everyone], [$visibility->panel, $visibility->counts, $visibility->names]);
    }

    private function react(string $member, string $code): void
    {
        $this->stage->repository->add(Reaction::of(\sprintf('r%04d', ++$this->sequence), $this->article, $member, $code, $this->stage->clock->now()));
    }

    /**
     * @param list<ReactionItem> $items
     *
     * @return list<string>
     */
    private function codes(array $items): array
    {
        return array_map(static fn (ReactionItem $item): string => $item->code, $items);
    }
}
