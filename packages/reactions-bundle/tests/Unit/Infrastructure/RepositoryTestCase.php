<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\ReactionsBundle\Domain\Exception\AlreadyReacted;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;

/**
 * What every store of reactions does.
 */
abstract class RepositoryTestCase extends TestCase
{
    protected ReactionRepository $store;

    private ResourceReference $one;

    private ResourceReference $two;

    abstract protected function store(): ReactionRepository;

    #[Override]
    protected function setUp(): void
    {
        $this->store = $this->store();
        $this->one = ResourceReference::of('article', '1');
        $this->two = ResourceReference::of('article', '2');
    }

    public function testAReactionReadBack(): void
    {
        self::assertSame([], $this->store->of($this->one, 'ada'));

        $this->add('r1', $this->one, 'ada', 'like');
        $reaction = $this->first($this->one, 'ada');

        self::assertInstanceOf(Reaction::class, $reaction);
        self::assertSame(['r1', 'article:1', 'ada', 'like', '2026-05-01 10:00:00'], [$reaction->id, $reaction->content, $reaction->member, $reaction->code(), $reaction->reactedAt()->format('Y-m-d H:i:s')]);
        self::assertSame([], $this->store->of($this->two, 'ada'));
        self::assertSame([], $this->store->of($this->one, 'linus'));
    }

    public function testEachCodeOncePerMemberAndContent(): void
    {
        $this->add('r1', $this->one, 'ada', 'like');
        $this->add('r2', $this->two, 'ada', 'like');
        $this->add('r3', $this->one, 'linus', 'like');

        try {
            $this->add('r4', $this->one, 'ada', 'like');
            self::fail('Accepted.');
        } catch (AlreadyReacted $alreadyReacted) {
            self::assertSame('The member already reacted "like" to "article:1".', $alreadyReacted->getMessage());
        }

        self::assertSame(['r1'], $this->ids($this->one, 'ada'));
    }

    public function testSeveralCodesOfAMemberOldestFirst(): void
    {
        $this->add('0003', $this->one, 'ada', 'thanks');
        $this->add('0001', $this->one, 'ada', 'like');
        $this->add('0002', $this->one, 'linus', 'like');
        $this->add('0004', $this->two, 'ada', 'love');

        self::assertSame(['0001', '0003'], $this->ids($this->one, 'ada'));
        self::assertSame(['article:1' => ['like', 'thanks']], $this->store->chosenBy('ada', ['article:1']));
        self::assertSame(['article:1' => ['like' => 2, 'thanks' => 1]], array_map(static function (array $codes): array {
            ksort($codes);

            return $codes;
        }, $this->store->counts(['article:1'])));
    }

    public function testAContentAndAMemberAreNotMixedUp(): void
    {
        $this->add('r1', $this->one, '2b', 'like');
        $this->add('r2', ResourceReference::of('article', '12'), 'b', 'like');

        self::assertSame(['r1'], $this->ids($this->one, '2b'));
        self::assertSame(['r2'], $this->ids(ResourceReference::of('article', '12'), 'b'));
    }

    public function testASavedChange(): void
    {
        $this->add('r1', $this->one, 'ada', 'like');
        $this->add('r2', $this->one, 'linus', 'like');
        $reaction = $this->first($this->one, 'ada');
        self::assertInstanceOf(Reaction::class, $reaction);

        $reaction->changeTo('thanks', new DateTimeImmutable('2026-06-01 09:00:00'));
        $this->store->save($reaction);

        self::assertSame(['thanks', '2026-06-01 09:00:00'], [$this->first($this->one, 'ada')?->code(), $this->first($this->one, 'ada')?->reactedAt()->format('Y-m-d H:i:s')]);
        self::assertSame('like', $this->first($this->one, 'linus')?->code());
        self::assertCount(1, $this->store->of($this->one, 'ada'));
    }

    public function testARemoval(): void
    {
        $this->add('r1', $this->one, 'ada', 'like');
        $this->add('r2', $this->one, 'linus', 'like');
        $this->add('r3', $this->one, 'ada', 'thanks');
        $reaction = $this->first($this->one, 'ada');
        self::assertInstanceOf(Reaction::class, $reaction);

        $this->store->remove($reaction);

        self::assertSame(['r3'], $this->ids($this->one, 'ada'));
        self::assertSame(['r2'], $this->ids($this->one, 'linus'));
    }

    public function testTheCountersOfSeveralContents(): void
    {
        $this->add('r1', $this->one, 'ada', 'like');
        $this->add('r2', $this->one, 'linus', 'like');
        $this->add('r3', $this->one, 'grace', 'thanks');
        $this->add('r4', $this->two, 'ada', 'thanks');
        $this->add('r5', ResourceReference::of('article', '3'), 'ada', 'like');

        $counts = $this->store->counts(['article:1', 'article:2', 'article:9']);
        ksort($counts);
        ksort($counts['article:1']);

        self::assertSame(['article:1' => ['like' => 2, 'thanks' => 1], 'article:2' => ['thanks' => 1]], $counts);
    }

    public function testWhatAMemberChose(): void
    {
        $this->add('r1', $this->one, 'ada', 'like');
        $this->add('r2', $this->two, 'ada', 'thanks');
        $this->add('r3', $this->two, 'linus', 'like');
        $this->add('r4', ResourceReference::of('article', '3'), 'ada', 'like');

        $chosen = $this->store->chosenBy('ada', ['article:1', 'article:2', 'article:9']);
        ksort($chosen);

        self::assertSame(['article:1' => ['like'], 'article:2' => ['thanks']], $chosen);
        self::assertSame([], $this->store->chosenBy('nobody', ['article:1']));
    }

    public function testTheFirstMembersOfEachCode(): void
    {
        $this->add('0001', $this->one, 'ada', 'like');
        $this->add('0002', $this->one, 'linus', 'like');
        $this->add('0003', $this->one, 'grace', 'like');
        $this->add('0004', $this->one, 'alan', 'thanks');
        $this->add('0005', $this->two, 'ada', 'like');
        $this->add('0006', ResourceReference::of('article', '3'), 'ada', 'like');

        $reactors = $this->store->reactors(['article:1', 'article:2'], 2);
        ksort($reactors);
        ksort($reactors['article:1']);

        self::assertSame(['article:1' => ['like' => ['ada', 'linus'], 'thanks' => ['alan']], 'article:2' => ['like' => ['ada']]], $reactors);
    }

    public function testTheReactionsOfAMember(): void
    {
        $this->add('0001', $this->one, 'ada', 'like');
        $this->add('0002', $this->one, 'linus', 'like');
        $this->add('0003', $this->two, 'ada', 'thanks');

        self::assertSame(['0001', '0003'], array_map(static fn (Reaction $reaction): string => $reaction->id, $this->store->ofMember('ada')));
        self::assertSame([], $this->store->ofMember('nobody'));
    }

    public function testErasingAMember(): void
    {
        $this->add('r1', $this->one, 'ada', 'like');
        $this->add('r2', $this->two, 'ada', 'like');
        $this->add('r3', $this->one, 'linus', 'like');

        $this->store->eraseMember('ada');
        $this->store->eraseMember('ada');

        self::assertSame([], $this->store->ofMember('ada'));
        self::assertCount(1, $this->store->ofMember('linus'));
    }

    public function testErasingAContent(): void
    {
        $this->add('r1', $this->one, 'ada', 'like');
        $this->add('r2', $this->one, 'linus', 'like');
        $this->add('r3', $this->two, 'ada', 'like');

        $this->store->eraseContent($this->one);

        self::assertSame([], $this->store->counts(['article:1']));
        self::assertSame(['article:2' => ['like' => 1]], $this->store->counts(['article:2']));
    }

    private function first(ResourceReference $content, string $member): ?Reaction
    {
        return $this->store->of($content, $member)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function ids(ResourceReference $content, string $member): array
    {
        return array_map(static fn (Reaction $reaction): string => $reaction->id, $this->store->of($content, $member));
    }

    private function add(string $id, ResourceReference $content, string $member, string $code): void
    {
        $this->store->add(Reaction::of($id, $content, $member, $code, new DateTimeImmutable('2026-05-01 10:00:00')));
    }
}
