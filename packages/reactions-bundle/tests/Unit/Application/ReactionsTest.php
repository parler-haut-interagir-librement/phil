<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\ReactionsBundle\Application\Event\ReactionAdded;
use Phil\ReactionsBundle\Application\Event\ReactionRemoved;
use Phil\ReactionsBundle\Application\Exception\ReactionLimitReached;
use Phil\ReactionsBundle\Application\Exception\ReactionRefused;
use Phil\ReactionsBundle\Application\Exception\TooManyReactions;
use Phil\ReactionsBundle\Application\Exception\UnknownReaction;
use Phil\ReactionsBundle\Application\Reactions;
use Phil\ReactionsBundle\Application\ReactionsPerMember;
use Phil\ReactionsBundle\Application\ReactionSubject;
use Phil\ReactionsBundle\Domain\Exception\AlreadyReacted;
use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Memory\InMemoryReactionRepository;
use Phil\ReactionsBundle\Tests\Fixtures\RecordingEvents;
use Phil\ReactionsBundle\Tests\Fixtures\Stage;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reactions::class)]
#[CoversClass(ReactionSubject::class)]
#[CoversClass(ReactionAdded::class)]
#[CoversClass(ReactionRemoved::class)]
#[CoversClass(ReactionRefused::class)]
#[CoversClass(UnknownReaction::class)]
#[CoversClass(TooManyReactions::class)]
#[CoversClass(ReactionLimitReached::class)]
#[CoversClass(ReactionsPerMember::class)]
final class ReactionsTest extends TestCase
{
    private Stage $stage;

    private ResourceReference $article;

    #[Override]
    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->article = ResourceReference::of('article', '1');
    }

    public function testAFirstReaction(): void
    {
        self::assertTrue($this->stage->reactions->react($this->article, 'ada', 'like'));

        $reaction = $this->first('ada');
        self::assertInstanceOf(Reaction::class, $reaction);
        self::assertSame(['article:1', 'ada', 'like', '2026-05-01 10:00:00'], [$reaction->content, $reaction->member, $reaction->code(), $reaction->reactedAt()->format('Y-m-d H:i:s')]);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7/', $reaction->id);
        self::assertEquals([new ReactionAdded('article:1', 'ada', 'like', $this->stage->clock->now())], $this->stage->dispatched);

        [$subject] = $this->stage->asked;
        self::assertSame(['article:1', 'ada', 'like'], [$subject->content->toString(), $subject->member, $subject->reaction->code]);
    }

    public function testTheSameReactionChangesNothing(): void
    {
        $this->stage->reactions->react($this->article, 'ada', 'like');
        $this->stage->dispatched = [];

        self::assertFalse($this->stage->reactions->react($this->article, 'ada', 'like'));
        self::assertSame([], $this->stage->dispatched);
    }

    public function testChangingIsARemovalThenAnAddition(): void
    {
        $this->stage->reactions->react($this->article, 'ada', 'like');
        $id = $this->first('ada')?->id;
        $this->stage->dispatched = [];
        $this->stage->clock->sleep(60);

        self::assertTrue($this->stage->reactions->react($this->article, 'ada', 'thanks'));

        $reaction = $this->first('ada');
        self::assertSame([$id, 'thanks', '2026-05-01 10:01:00'], [$reaction?->id, $reaction?->code(), $reaction?->reactedAt()->format('Y-m-d H:i:s')]);
        $now = $this->stage->clock->now();
        self::assertSame(1, $this->stage->repository->saved);
        self::assertEquals([new ReactionRemoved('article:1', 'ada', 'like', $now), new ReactionAdded('article:1', 'ada', 'thanks', $now)], $this->stage->dispatched);
    }

    public function testACodeTheSiteDoesNotOffer(): void
    {
        $this->expectExceptionObject(UnknownReaction::code('nothing'));

        $this->stage->reactions->react($this->article, 'ada', 'nothing');
    }

    public function testRefusedByTheAccessBeforeBeingCounted(): void
    {
        $this->stage->refused = ['like'];
        $this->stage->tooMany = true;

        try {
            $this->stage->reactions->react($this->article, 'ada', 'like');
            self::fail('Accepted.');
        } catch (ReactionRefused $reactionRefused) {
            self::assertSame('The reaction "like" is refused to this member.', $reactionRefused->getMessage());
        }

        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
    }

    public function testHeldBackByTheThrottle(): void
    {
        $this->stage->tooMany = true;

        foreach ([fn (): bool => $this->stage->reactions->react($this->article, 'ada', 'like'), fn (): bool => $this->stage->reactions->withdraw($this->article, 'ada')] as $change) {
            try {
                $change();
                self::fail('Accepted.');
            } catch (TooManyReactions $tooManyReactions) {
                self::assertSame(12, $tooManyReactions->retryAfter);
                self::assertSame('Too many reactions: try again in 12 seconds.', $tooManyReactions->getMessage());
            }
        }

        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
    }

    public function testWithdrawing(): void
    {
        $this->stage->reactions->react($this->article, 'ada', 'like');
        $this->stage->dispatched = [];
        $this->stage->refused = ['like'];

        self::assertTrue($this->stage->reactions->withdraw($this->article, 'ada'));
        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
        self::assertEquals([new ReactionRemoved('article:1', 'ada', 'like', $this->stage->clock->now())], $this->stage->dispatched);

        $this->stage->dispatched = [];
        self::assertFalse($this->stage->reactions->withdraw($this->article, 'ada'));
        self::assertSame([], $this->stage->dispatched);
    }

    public function testTwoRequestsRacingToTheSameReaction(): void
    {
        $store = $this->racing([[]], refusesTheAddition: true);

        self::assertFalse($this->reactionsOn($store)->react($this->article, 'ada', 'like'));
        self::assertSame([], $store->removed);
        self::assertSame([], $this->stage->dispatched);
    }

    public function testTwoRequestsRacingToTheOnlyReactionKeepTheOldest(): void
    {
        $older = Reaction::of('00000000-0000-7000-8000-000000000000', $this->article, 'ada', 'thanks', new DateTimeImmutable('2026-01-01'));
        $store = $this->racing([[], [$older, $this->added()]]);

        self::assertFalse($this->reactionsOn($store)->react($this->article, 'ada', 'like'));
        self::assertSame(['like'], array_map(static fn (Reaction $reaction): string => $reaction->code(), $store->removed));
        self::assertSame([], $this->stage->dispatched);
    }

    public function testTheOldestReactionOfARaceIsKept(): void
    {
        $newer = Reaction::of('ffffffff-ffff-7fff-bfff-ffffffffffff', $this->article, 'ada', 'thanks', new DateTimeImmutable('2026-01-01'));
        $store = $this->racing([[], [$this->added(), $newer]]);

        self::assertTrue($this->reactionsOn($store)->react($this->article, 'ada', 'like'));
        self::assertSame([$newer], $store->removed);
        self::assertCount(1, $this->stage->dispatched);
    }

    public function testTwoRequestsRacingPastTheCap(): void
    {
        $first = Reaction::of('00000000-0000-7000-8000-000000000001', $this->article, 'ada', 'thanks', new DateTimeImmutable('2026-01-01'));
        $second = Reaction::of('00000000-0000-7000-8000-000000000002', $this->article, 'ada', 'spark', new DateTimeImmutable('2026-01-01'));
        $store = $this->racing([[$first], [$first, $second, $this->added()]]);

        self::assertFalse($this->reactionsOn($store, 2)->react($this->article, 'ada', 'like'));
        self::assertSame(['like'], array_map(static fn (Reaction $reaction): string => $reaction->code(), $store->removed));
        self::assertSame([], $this->stage->dispatched);
    }

    public function testSeveralReactionsUpToTheCap(): void
    {
        $stage = new Stage(2);

        self::assertTrue($stage->reactions->react($this->article, 'ada', 'like'));
        $stage->clock->sleep(1);
        self::assertTrue($stage->reactions->react($this->article, 'ada', 'thanks'));
        self::assertFalse($stage->reactions->react($this->article, 'ada', 'like'));
        self::assertSame(0, $stage->repository->saved);
        self::assertEquals([new ReactionAdded('article:1', 'ada', 'like', new DateTimeImmutable('2026-05-01 10:00:00')), new ReactionAdded('article:1', 'ada', 'thanks', $stage->clock->now())], $stage->dispatched);

        try {
            $stage->reactions->react($this->article, 'ada', 'spark');
            self::fail('Accepted.');
        } catch (ReactionLimitReached $reactionLimitReached) {
            self::assertSame('A member gives at most 2 reactions to a content.', $reactionLimitReached->getMessage());
        }

        self::assertSame(['like', 'thanks'], $this->codes($stage, 'ada'));
        self::assertTrue($stage->reactions->react($this->article, 'linus', 'spark'));
    }

    public function testWithdrawingOneOfSeveral(): void
    {
        $stage = new Stage(3);
        $stage->reactions->react($this->article, 'ada', 'like');
        $stage->reactions->react($this->article, 'ada', 'thanks');
        $stage->reactions->react($this->article, 'ada', 'spark');
        $stage->dispatched = [];

        self::assertTrue($stage->reactions->withdraw($this->article, 'ada', 'thanks'));
        self::assertFalse($stage->reactions->withdraw($this->article, 'ada', 'thanks'));
        self::assertSame(['like', 'spark'], $this->codes($stage, 'ada'));
        self::assertEquals([new ReactionRemoved('article:1', 'ada', 'thanks', $stage->clock->now())], $stage->dispatched);

        self::assertTrue($stage->reactions->react($this->article, 'ada', 'gnome'));
        self::assertTrue($stage->reactions->withdraw($this->article, 'ada'));
        self::assertSame([], $this->codes($stage, 'ada'));
    }

    public function testTheCapOfASite(): void
    {
        self::assertTrue(new ReactionsPerMember()->replaces());
        self::assertFalse(new ReactionsPerMember(2)->replaces());
        self::assertTrue(new ReactionsPerMember(2)->admitsAnother(1));
        self::assertFalse(new ReactionsPerMember(2)->admitsAnother(2));
        self::assertSame(ReactionsPerMember::MAX, new ReactionsPerMember(20)->cap);

        foreach ([0, 21] as $cap) {
            try {
                new ReactionsPerMember($cap);
                self::fail('Accepted.');
            } catch (InvalidReaction $invalidReaction) {
                self::assertSame(\sprintf('A member gives 1 to 20 reactions to a content, not %d.', $cap), $invalidReaction->getMessage());
            }
        }
    }

    public function testWithoutADispatcher(): void
    {
        $reactions = new Reactions(new InMemoryReactionRepository(), $this->stage->catalog, $this->stage, $this->stage, $this->stage->clock);

        self::assertTrue($reactions->react($this->article, 'ada', 'like'));
        self::assertTrue($reactions->react($this->article, 'ada', 'thanks'));
        self::assertTrue($reactions->withdraw($this->article, 'ada'));
    }

    public function testErasingAContent(): void
    {
        $other = ResourceReference::of('article', '2');
        $this->stage->reactions->react($this->article, 'ada', 'like');
        $this->stage->reactions->react($other, 'ada', 'like');

        $this->stage->reactions->eraseContent($this->article);

        self::assertSame([], $this->stage->repository->of($this->article, 'ada'));
        self::assertCount(1, $this->stage->repository->of($other, 'ada'));
    }

    public function testTheOtherRefusals(): void
    {
        self::assertSame('The request does not come from a page of this site.', ReactionRefused::untrustedRequest()->getMessage());
        self::assertSame('Only a member can react.', ReactionRefused::noMember()->getMessage());
        self::assertSame('phil_reactions.react', Reactions::REACT);
    }

    private function first(string $member): ?Reaction
    {
        return $this->stage->repository->of($this->article, $member)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function codes(Stage $stage, string $member): array
    {
        return array_map(static fn (Reaction $reaction): string => $reaction->code(), $stage->repository->of($this->article, $member));
    }

    /**
     * The reaction a racing store says was just added: the one with the code `like` that the test asked for.
     */
    private function added(): Reaction
    {
        return Reaction::of('added', ResourceReference::of('article', '1'), 'ada', 'like', new DateTimeImmutable('2026-01-01'));
    }

    private function reactionsOn(ReactionRepository $store, int $perMember = 1): Reactions
    {
        return new Reactions($store, $this->stage->catalog, $this->stage, $this->stage, $this->stage->clock, new RecordingEvents($this->stage->dispatched), new ReactionsPerMember($perMember));
    }

    /**
     * A store whose reads of a member's reactions answer, one after the other, what another request left meanwhile; the
     * reaction added stands for the one named `added` in those answers.
     *
     * @param list<list<Reaction>> $reads
     *
     * @return ReactionRepository&object{removed: list<Reaction>}
     */
    private function racing(array $reads, bool $refusesTheAddition = false): ReactionRepository
    {
        return new class($reads, $refusesTheAddition) implements ReactionRepository {
            /** @var list<Reaction> */
            public array $removed = [];

            private ?Reaction $added = null;

            /**
             * @param list<list<Reaction>> $reads
             */
            public function __construct(private array $reads, private readonly bool $refusesTheAddition)
            {
            }

            #[Override]
            public function of(ResourceReference $content, string $member): array
            {
                $read = array_shift($this->reads) ?? [];

                return array_map(fn (Reaction $reaction): Reaction => 'added' === $reaction->id && $this->added instanceof Reaction ? $this->added : $reaction, $read);
            }

            #[Override]
            public function add(Reaction $reaction): void
            {
                if ($this->refusesTheAddition) {
                    throw AlreadyReacted::on($reaction->content, $reaction->code());
                }

                $this->added = $reaction;
            }

            #[Override]
            public function save(Reaction $reaction): void
            {
            }

            #[Override]
            public function remove(Reaction $reaction): void
            {
                $this->removed[] = $reaction;
            }

            #[Override]
            public function counts(array $contents): array
            {
                return [];
            }

            #[Override]
            public function chosenBy(string $member, array $contents): array
            {
                return [];
            }

            #[Override]
            public function reactors(array $contents, int $perCode): array
            {
                return [];
            }

            #[Override]
            public function ofMember(string $member): array
            {
                return [];
            }

            #[Override]
            public function eraseMember(string $member): void
            {
            }

            #[Override]
            public function eraseContent(ResourceReference $content): void
            {
            }
        };
    }
}
