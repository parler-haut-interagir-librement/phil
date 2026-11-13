<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Phil\ResourcePermissions\Exception\UnknownRelation;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Phil\ResourcePermissions\Tests\Unit\Model\ModelTest;
use Phil\ResourcePermissions\Traversal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(RelationChecker::class)]
#[CoversClass(Traversal::class)]
#[CoversClass(TraversalBudgetExceeded::class)]
final class RelationCheckerTest extends TestCase
{
    private MockClock $clock;

    private InMemoryTupleStore $store;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->store = new InMemoryTupleStore($this->clock);
    }

    public function testADirectTupleGivesTheRelationToItsSubjectOnly(): void
    {
        $this->write('doc:1#viewer@member:ann');
        $checker = $this->checker();

        self::assertTrue($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
        self::assertFalse($checker->check($this->ref('member:bob'), 'viewer', $this->ref('doc:1')));
        self::assertFalse($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:2')));
        self::assertSame(['doc:1#viewer@member:ann'], $checker->explain($this->ref('member:ann'), 'viewer', $this->ref('doc:1'))?->lines());
        self::assertNull($checker->explain($this->ref('member:bob'), 'viewer', $this->ref('doc:1')));
    }

    public function testEveryObjectOfAKind(): void
    {
        $this->write('doc:1#viewer@member:*');
        $checker = $this->checker();

        self::assertTrue($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
        self::assertTrue($checker->check(Subject::every('member'), 'viewer', $this->ref('doc:1')));
        self::assertFalse($checker->check($this->ref('visitor:ann'), 'viewer', $this->ref('doc:1')));
        self::assertFalse($checker->check(Subject::fromString('group:g#member'), 'viewer', $this->ref('doc:1')));
        self::assertSame(['doc:1#viewer@member:*'], $checker->explain($this->ref('member:zed'), 'viewer', $this->ref('doc:1'))?->lines());
    }

    public function testNestedGroups(): void
    {
        $this->write('doc:1#viewer@group:staff#member', 'group:staff#member@group:editors#member', 'group:editors#member@member:ann');
        $checker = $this->checker();

        self::assertTrue($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
        self::assertFalse($checker->check($this->ref('member:bob'), 'viewer', $this->ref('doc:1')));
        self::assertTrue($checker->check(Subject::fromString('group:editors#member'), 'viewer', $this->ref('doc:1')));
        self::assertSame(
            ['doc:1#viewer@group:staff#member', 'group:staff#member@group:editors#member', 'group:editors#member@member:ann'],
            $checker->explain($this->ref('member:ann'), 'viewer', $this->ref('doc:1'))?->lines(),
        );
    }

    public function testACycleOfGroupsEnds(): void
    {
        $this->write('doc:1#viewer@group:a#member', 'group:a#member@group:b#member', 'group:b#member@group:a#member', 'group:b#member@member:ann');
        $checker = $this->checker();

        self::assertTrue($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
        self::assertFalse($checker->check($this->ref('member:bob'), 'viewer', $this->ref('doc:1')));
        self::assertSame(['1'], $checker->objects($this->ref('member:ann'), 'viewer', 'doc')->ids());
    }

    public function testComputedAndInheritedRelations(): void
    {
        $this->write(
            'folder:root#editor@group:g#member',
            'folder:sub#parent@folder:root',
            'doc:1#parent@folder:sub',
            'group:g#member@member:ann',
            'folder:other#viewer@member:bob',
        );
        $checker = $this->checker();

        self::assertTrue($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
        self::assertTrue($checker->check($this->ref('member:ann'), 'viewer', $this->ref('folder:root')));
        self::assertFalse($checker->check($this->ref('member:bob'), 'viewer', $this->ref('doc:1')));
        self::assertSame(
            ['doc:1#parent@folder:sub', 'folder:sub#parent@folder:root', 'folder:root#editor@group:g#member', 'group:g#member@member:ann'],
            $checker->explain($this->ref('member:ann'), 'viewer', $this->ref('doc:1'))?->lines(),
        );
    }

    public function testFromRelatedSkipsWhatIsNotOneObjectOrLacksTheRelation(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('team', ['viewer' => Rewrite::direct('member')]),
            TypeDefinition::of('doc', [
                'parent' => Rewrite::direct('member', 'team', 'team#viewer', 'team:*'),
                'viewer' => Rewrite::fromRelated('parent', 'viewer'),
            ]),
        );
        $this->store->update([], [
            $this->tuple('doc:1#parent@member:ann'),
            $this->tuple('doc:1#parent@team:t#viewer'),
            $this->tuple('doc:1#parent@team:*'),
            $this->tuple('team:t#viewer@member:ann'),
        ]);
        $checker = new RelationChecker($model, $this->store);

        self::assertFalse($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));

        $this->store->update([], [$this->tuple('doc:1#parent@team:t')]);

        self::assertTrue($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
    }

    public function testIntersectionNeedsEveryOperand(): void
    {
        $checker = $this->operations();
        $this->write('page:1#member@member:ann', 'page:1#paid@member:ann', 'page:1#member@member:bob');

        self::assertTrue($checker->check($this->ref('member:ann'), 'reader', $this->ref('page:1')));
        self::assertFalse($checker->check($this->ref('member:bob'), 'reader', $this->ref('page:1')));
        self::assertSame(['page:1#member@member:ann', 'page:1#paid@member:ann'], $checker->explain($this->ref('member:ann'), 'reader', $this->ref('page:1'))?->lines());
    }

    public function testExclusionRemovesTheSubtracted(): void
    {
        $checker = $this->operations();
        $this->write('page:1#member@member:ann', 'page:1#member@member:bob', 'page:1#blocked@member:bob');

        self::assertTrue($checker->check($this->ref('member:ann'), 'commenter', $this->ref('page:1')));
        self::assertFalse($checker->check($this->ref('member:bob'), 'commenter', $this->ref('page:1')));
        self::assertFalse($checker->check($this->ref('member:cy'), 'commenter', $this->ref('page:1')));
        self::assertSame(['page:1#member@member:ann'], $checker->explain($this->ref('member:ann'), 'commenter', $this->ref('page:1'))?->lines());
    }

    public function testAnExclusionWhoseSubtractedSideMetACycleRefuses(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('page', [
                'member' => Rewrite::direct('member'),
                'blocked' => Rewrite::union(Rewrite::direct('member'), Rewrite::computed('banned')),
                'banned' => Rewrite::computed('blocked'),
                'commenter' => Rewrite::exclusion(Rewrite::computed('member'), Rewrite::computed('blocked')),
                'open' => Rewrite::exclusion(Rewrite::computed('member'), Rewrite::direct('member')),
            ]),
        );
        $this->write('page:1#member@member:ann');
        $checker = new RelationChecker($model, $this->store);

        self::assertFalse($checker->check($this->ref('member:ann'), 'commenter', $this->ref('page:1')));
        self::assertTrue($checker->check($this->ref('member:ann'), 'open', $this->ref('page:1')));
    }

    public function testANoFoundInsideACycleIsNotKeptForLater(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('page', [
                'p' => Rewrite::union(Rewrite::computed('q'), Rewrite::direct('member')),
                'q' => Rewrite::union(Rewrite::computed('p'), Rewrite::direct('member')),
                'both' => Rewrite::intersection(Rewrite::computed('q'), Rewrite::computed('p')),
            ]),
        );
        $this->write('page:1#q@member:ann');

        self::assertTrue(new RelationChecker($model, $this->store)->check($this->ref('member:ann'), 'both', $this->ref('page:1')));
    }

    public function testAStepAlreadyAnsweredIsNotReadAgain(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('page', [
                'c' => Rewrite::direct('member'),
                'a' => Rewrite::computed('c'),
                'b' => Rewrite::computed('c'),
                'v' => Rewrite::union(Rewrite::computed('a'), Rewrite::computed('b')),
                'w' => Rewrite::intersection(Rewrite::computed('a'), Rewrite::computed('b')),
            ]),
        );

        self::assertFalse(new RelationChecker($model, $this->store, new Budget(maxReads: 2))->check($this->ref('member:ann'), 'v', $this->ref('page:1')));
        $this->write('page:1#c@member:ann');
        self::assertTrue(new RelationChecker($model, $this->store, new Budget(maxReads: 1))->check($this->ref('member:ann'), 'w', $this->ref('page:1')));
    }

    public function testAListingWithoutCycleGathersInOneRound(): void
    {
        $model = new Model(TypeDefinition::of('member'), TypeDefinition::of('page', ['viewer' => Rewrite::direct('member')]));
        $this->write('page:1#viewer@member:ann', 'page:2#viewer@member:ann');

        self::assertSame(['1', '2'], new RelationChecker($model, $this->store, new Budget(maxReads: 1))->objects($this->ref('member:ann'), 'viewer', 'page')->ids());
    }

    public function testAnExpiredTupleGivesNothing(): void
    {
        $this->store->update([], [new RelationTuple($this->ref('doc:1'), 'viewer', Subject::fromString('member:ann'), $this->clock->now()->modify('+1 day'))]);
        $checker = $this->checker();

        self::assertTrue($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
        $this->clock->sleep(86_400);
        self::assertFalse($checker->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
    }

    public function testAnUnknownRelationIsRefused(): void
    {
        $this->expectException(UnknownRelation::class);

        $this->checker()->check($this->ref('member:ann'), 'owner', $this->ref('doc:1'));
    }

    public function testAnUnknownKindIsRefusedBeforeAnyRead(): void
    {
        $this->expectException(UnknownRelation::class);

        $this->checker(new Budget(maxReads: 1))->objects($this->ref('member:ann'), 'viewer', 'page');
    }

    public function testTooDeep(): void
    {
        $this->write('doc:1#viewer@group:g1#member');

        foreach (range(1, 5) as $i) {
            $this->write(\sprintf('group:g%d#member@group:g%d#member', $i, $i + 1));
        }

        $this->write('group:g6#member@member:ann');

        self::assertTrue($this->checker(new Budget(maxDepth: 6))->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
        $this->expectException(TraversalBudgetExceeded::class);
        $this->expectExceptionMessageIsOrContains('The traversal went deeper than 5 steps.');

        $this->checker(new Budget(maxDepth: 5))->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1'));
    }

    public function testTooManyReads(): void
    {
        $this->write('doc:1#viewer@group:g#member', 'group:g#member@member:ann');

        self::assertTrue($this->checker(new Budget(maxReads: 3))->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
        $this->expectException(TraversalBudgetExceeded::class);
        $this->expectExceptionMessageIsOrContains('The traversal read the store more than 2 times.');

        $this->checker(new Budget(maxReads: 2))->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1'));
    }

    public function testTooManySetsBehindOneRelation(): void
    {
        $this->write('doc:1#viewer@group:a#member', 'doc:1#viewer@group:b#member', 'group:b#member@member:ann');

        self::assertTrue($this->checker(new Budget(maxFanOut: 2))->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
        $this->write('doc:1#viewer@group:c#member');
        $this->expectException(TraversalBudgetExceeded::class);
        $this->expectExceptionMessageIsOrContains('The relation "viewer" holds more than 2 subject sets or related objects to follow.');

        $this->checker(new Budget(maxFanOut: 2))->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1'));
    }

    public function testTooManyObjectsOnOneSubjectDoesNotCountAgainstTheFanOut(): void
    {
        $this->write('doc:1#viewer@member:a', 'doc:1#viewer@member:b', 'doc:1#viewer@member:c', 'doc:1#viewer@group:g#member', 'group:g#member@member:ann');

        self::assertTrue($this->checker(new Budget(maxFanOut: 1))->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
    }

    public function testObjectsByDirectTuplesEveryAndSets(): void
    {
        $this->write(
            'doc:3#viewer@member:ann',
            'doc:1#viewer@member:*',
            'doc:2#viewer@group:g#member',
            'group:g#member@member:ann',
            'doc:4#viewer@member:bob',
            'folder:f#editor@group:g#member',
            'doc:5#parent@folder:f',
        );
        $checker = $this->checker();

        $list = $checker->objects($this->ref('member:ann'), 'viewer', 'doc');
        self::assertSame(['doc:1', 'doc:2', 'doc:3', 'doc:5'], array_map(static fn (ResourceReference $object): string => $object->toString(), $list->objects));
        self::assertTrue($list->complete);
        self::assertSame(['1', '4'], $checker->objects($this->ref('member:bob'), 'viewer', 'doc')->ids());
        self::assertSame([], $checker->objects($this->ref('visitor:x'), 'viewer', 'doc')->ids());
        self::assertSame(['f'], $checker->objects($this->ref('member:ann'), 'viewer', 'folder')->ids());
    }

    public function testObjectsAreCutAtTheLimit(): void
    {
        $this->write('doc:1#viewer@member:ann', 'doc:2#viewer@member:ann', 'doc:3#viewer@member:ann');
        $checker = $this->checker();

        $list = $checker->objects($this->ref('member:ann'), 'viewer', 'doc', 2);
        self::assertSame(['1', '2'], $list->ids());
        self::assertFalse($list->complete);
        self::assertTrue($checker->objects($this->ref('member:ann'), 'viewer', 'doc', 3)->complete);
    }

    public function testObjectsAreCutAtTheCandidates(): void
    {
        $this->write('doc:1#viewer@member:ann', 'doc:2#viewer@member:ann', 'doc:3#viewer@member:ann');

        $list = $this->checker(new Budget(maxCandidates: 2))->objects($this->ref('member:ann'), 'viewer', 'doc');
        self::assertSame(['1', '2'], $list->ids());
        self::assertFalse($list->complete);
        self::assertTrue($this->checker(new Budget(maxCandidates: 3))->objects($this->ref('member:ann'), 'viewer', 'doc')->complete);
    }

    public function testObjectsThroughGroupsInsideGroups(): void
    {
        $this->write(
            'group:a#member@member:ann',
            'group:b#member@group:a#member',
            'group:c#member@group:b#member',
            'doc:1#viewer@group:c#member',
            'doc:2#viewer@group:b#member',
        );

        self::assertSame(['1', '2'], $this->checker()->objects($this->ref('member:ann'), 'viewer', 'doc')->ids());
        self::assertSame(['a', 'b', 'c'], $this->checker()->objects($this->ref('member:ann'), 'member', 'group')->ids());
    }

    public function testObjectsKeepOnlyWhatChecks(): void
    {
        $checker = $this->operations();
        $this->write('page:1#member@member:ann', 'page:2#member@member:ann', 'page:2#paid@member:ann', 'page:3#member@member:ann', 'page:3#blocked@member:ann');

        self::assertSame(['2'], $checker->objects($this->ref('member:ann'), 'reader', 'page')->ids());
        self::assertSame(['1', '2'], $checker->objects($this->ref('member:ann'), 'commenter', 'page')->ids());
    }

    public function testObjectsOfASet(): void
    {
        $this->write('doc:1#viewer@group:g#member', 'doc:2#viewer@member:ann');

        self::assertSame(['1'], $this->checker()->objects(Subject::fromString('group:g#member'), 'viewer', 'doc')->ids());
    }

    public function testTheLimitOfAListing(): void
    {
        $checker = $this->checker();
        self::assertSame([], $checker->objects($this->ref('member:ann'), 'viewer', 'doc', 1)->ids());
        self::assertSame([], $checker->objects($this->ref('member:ann'), 'viewer', 'doc', RelationChecker::MAX_LIMIT)->ids());

        foreach ([0, RelationChecker::MAX_LIMIT + 1] as $limit) {
            try {
                $checker->objects($this->ref('member:ann'), 'viewer', 'doc', $limit);
                self::fail('The limit should be refused.');
            } catch (InvalidRelation $invalid) {
                self::assertSame('A listing asks for 1 to 1000 objects.', $invalid->getMessage());
            }
        }
    }

    public function testAListingWithinItsBudget(): void
    {
        $this->write('doc:1#viewer@member:ann');

        $this->expectException(TraversalBudgetExceeded::class);

        $this->checker(new Budget(maxReads: 1))->objects($this->ref('member:ann'), 'viewer', 'doc');
    }

    public function testAListingDepth(): void
    {
        $this->expectException(TraversalBudgetExceeded::class);
        $this->expectExceptionMessageIsOrContains('deeper than 1 steps');

        $this->checker(new Budget(maxDepth: 1))->objects($this->ref('member:ann'), 'viewer', 'doc');
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }

    private function tuple(string $tuple): RelationTuple
    {
        [$object, $rest] = explode('#', $tuple, 2);
        [$relation, $subject] = explode('@', $rest, 2);

        return new RelationTuple($this->ref($object), $relation, Subject::fromString($subject));
    }

    private function write(string ...$tuples): void
    {
        $this->store->update([], array_map($this->tuple(...), array_values($tuples)));
    }

    private function checker(Budget $budget = new Budget()): RelationChecker
    {
        return new RelationChecker(ModelTest::model(), $this->store, $budget);
    }

    private function operations(): RelationChecker
    {
        return new RelationChecker(new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('page', [
                'member' => Rewrite::direct('member'),
                'paid' => Rewrite::direct('member'),
                'blocked' => Rewrite::direct('member'),
                'reader' => Rewrite::intersection(Rewrite::computed('member'), Rewrite::computed('paid')),
                'commenter' => Rewrite::exclusion(Rewrite::computed('member'), Rewrite::computed('blocked')),
            ]),
        ), $this->store);
    }
}
