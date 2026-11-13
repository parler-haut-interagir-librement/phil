<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use Closure;
use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
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

/**
 * The exact reach of the limits: the least depth a walk needs, the default size of a listing, and steps that only
 * look alike.
 */
#[CoversClass(RelationChecker::class)]
#[CoversClass(Traversal::class)]
final class RelationCheckerLimitsTest extends TestCase
{
    private InMemoryTupleStore $store;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryTupleStore(new MockClock('2026-11-13 10:00:00 UTC'));
    }

    public function testACheckThroughComputedRelationsNeedsOneDepthPerHop(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('doc', [
                'a' => Rewrite::computed('b'),
                'b' => Rewrite::computed('c'),
                'c' => Rewrite::direct('member'),
            ]),
        );
        $this->write('doc:1#c@member:ann');

        self::assertSame(2, $this->leastDepth(fn (Budget $budget): bool => new RelationChecker($model, $this->store, $budget)->check($this->ref('member:ann'), 'a', $this->ref('doc:1'))));
    }

    public function testACheckThroughSubjectSetsNeedsOneDepthPerSet(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('group', ['member' => Rewrite::direct('member', 'group#member')]),
            TypeDefinition::of('doc', ['viewer' => Rewrite::direct('member', 'group#member')]),
        );
        $this->write('doc:1#viewer@group:a#member', 'group:a#member@group:b#member', 'group:b#member@member:ann');

        self::assertSame(2, $this->leastDepth(fn (Budget $budget): bool => new RelationChecker($model, $this->store, $budget)->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1'))));
    }

    public function testACheckThroughRelatedObjectsNeedsOneDepthPerObject(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('doc', [
                'parent' => Rewrite::direct('doc'),
                'viewer' => Rewrite::union(Rewrite::direct('member'), Rewrite::fromRelated('parent', 'viewer')),
            ]),
        );
        $this->write('doc:1#parent@doc:2', 'doc:2#parent@doc:3', 'doc:3#viewer@member:ann');

        self::assertSame(2, $this->leastDepth(fn (Budget $budget): bool => new RelationChecker($model, $this->store, $budget)->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1'))));
    }

    public function testAListingGathersOneDepthPerComputedHop(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('doc', [
                'l3' => Rewrite::direct('member'),
                'l2' => Rewrite::computed('l3'),
                'l1' => Rewrite::computed('l2'),
                'a' => Rewrite::union(Rewrite::direct('member'), Rewrite::computed('l1')),
            ]),
        );
        $this->write('doc:1#a@member:ann');

        self::assertSame(3, $this->leastDepth(fn (Budget $budget): array => new RelationChecker($model, $this->store, $budget)->objects($this->ref('member:ann'), 'a', 'doc')->ids()));
    }

    public function testAListingGathersOneDepthPerSubjectSet(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('g3', ['m' => Rewrite::direct('member')]),
            TypeDefinition::of('g2', ['m' => Rewrite::direct('member', 'g3#m')]),
            TypeDefinition::of('g1', ['m' => Rewrite::direct('member', 'g2#m')]),
            TypeDefinition::of('doc', ['a' => Rewrite::union(Rewrite::direct('member'), Rewrite::direct('g1#m'))]),
        );
        $this->write('doc:1#a@member:ann');

        self::assertSame(3, $this->leastDepth(fn (Budget $budget): array => new RelationChecker($model, $this->store, $budget)->objects($this->ref('member:ann'), 'a', 'doc')->ids()));
    }

    public function testAListingGathersOneDepthPerRelatedObject(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('k3', ['b' => Rewrite::direct('member')]),
            TypeDefinition::of('k2', ['parent' => Rewrite::direct('k3'), 'b' => Rewrite::fromRelated('parent', 'b')]),
            TypeDefinition::of('k1', ['parent' => Rewrite::direct('k2'), 'b' => Rewrite::fromRelated('parent', 'b')]),
            TypeDefinition::of('doc', [
                'parent' => Rewrite::direct('k1'),
                'a' => Rewrite::union(Rewrite::direct('member'), Rewrite::fromRelated('parent', 'b')),
            ]),
        );
        $this->write('doc:1#a@member:ann');

        self::assertSame(3, $this->leastDepth(fn (Budget $budget): array => new RelationChecker($model, $this->store, $budget)->objects($this->ref('member:ann'), 'a', 'doc')->ids()));
    }

    public function testAListingChecksAsDeepAsACheckDoes(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('doc', [
                'p3' => Rewrite::direct('member'),
                'p2' => Rewrite::computed('p3'),
                'p1' => Rewrite::computed('p2'),
                'both' => Rewrite::direct('member'),
                'reader' => Rewrite::intersection(Rewrite::direct('member'), Rewrite::computed('p1')),
            ]),
        );
        $this->write('doc:1#reader@member:ann', 'doc:1#p3@member:ann');

        self::assertSame(3, $this->leastDepth(fn (Budget $budget): array => new RelationChecker($model, $this->store, $budget)->objects($this->ref('member:ann'), 'reader', 'doc')->ids()));
    }

    public function testAnExclusionWhoseBaseFailsReadsNothingMore(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('page', [
                'm3' => Rewrite::direct('member'),
                'm2' => Rewrite::computed('m3'),
                'm1' => Rewrite::computed('m2'),
                'base' => Rewrite::direct('member'),
                'reader' => Rewrite::exclusion(Rewrite::computed('base'), Rewrite::computed('m1')),
            ]),
        );
        $checker = new RelationChecker($model, $this->store, new Budget(maxDepth: 1));

        self::assertFalse($checker->check($this->ref('member:ann'), 'reader', $this->ref('page:1')));
    }

    public function testAListingStopsAtOneHundredByDefault(): void
    {
        $tuples = [];

        for ($i = 100; $i < 201; ++$i) {
            $tuples[] = 'doc:'.$i.'#viewer@member:ann';
        }

        $this->write(...$tuples);
        $list = new RelationChecker(ModelTest::model(), $this->store)->objects($this->ref('member:ann'), 'viewer', 'doc');

        self::assertCount(100, $list->ids());
        self::assertFalse($list->complete);
    }

    public function testStepsThatOnlyLookAlikeAreNotTheSameStep(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('doc', [
                'parent' => Rewrite::direct('doc'),
                'iewer' => Rewrite::direct('member'),
                'viewer' => Rewrite::fromRelated('parent', 'iewer'),
            ]),
        );
        $this->write('doc:1#parent@doc:1v', 'doc:1v#iewer@member:ann');

        self::assertTrue(new RelationChecker($model, $this->store)->check($this->ref('member:ann'), 'viewer', $this->ref('doc:1')));
    }

    public function testGatheredStepsThatOnlyLookAlikeAreNotTheSameStep(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('docx', ['v' => Rewrite::direct('member')]),
            TypeDefinition::of('doc', [
                'parent' => Rewrite::direct('docx'),
                'xv' => Rewrite::fromRelated('parent', 'v'),
            ]),
        );
        $this->write('doc:1#parent@docx:7', 'docx:7#v@member:ann');

        self::assertSame(['1'], new RelationChecker($model, $this->store)->objects($this->ref('member:ann'), 'xv', 'doc')->ids());
    }

    public function testAListingReadsOnceForTheRelatedKindsThatAreOneObject(): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('team', ['viewer' => Rewrite::direct('member')]),
            TypeDefinition::of('doc', [
                'parent' => Rewrite::direct('team', 'team#viewer', 'team:*'),
                'viewer' => Rewrite::fromRelated('parent', 'viewer'),
            ]),
        );
        $this->store->update([], [
            new RelationTuple($this->ref('doc:1'), 'parent', Subject::of($this->ref('team:t'))),
            new RelationTuple($this->ref('team:t'), 'viewer', Subject::of($this->ref('member:ann'))),
        ]);

        self::assertSame(['1'], new RelationChecker($model, $this->store)->objects($this->ref('member:ann'), 'viewer', 'doc')->ids());
        self::assertSame(2, $this->leastReads(fn (Budget $budget): array => new RelationChecker($model, $this->store, $budget)->objects($this->ref('member:ann'), 'viewer', 'doc')->ids()));
    }

    public function testTheBudgetIsStillRefusedAtItsEdges(): void
    {
        $this->expectException(InvalidRelation::class);

        new RelationChecker(new Model(TypeDefinition::of('member')), $this->store)->objects($this->ref('member:ann'), 'viewer', 'doc', 0);
    }

    /**
     * @param Closure(Budget): mixed $run
     */
    private function leastDepth(Closure $run): int
    {
        for ($depth = 1; $depth <= 12; ++$depth) {
            try {
                $run(new Budget(maxDepth: $depth));

                return $depth;
            } catch (TraversalBudgetExceeded) {
            }
        }

        self::fail('No depth up to 12 was enough.');
    }

    /**
     * @param Closure(Budget): mixed $run
     */
    private function leastReads(Closure $run): int
    {
        for ($reads = 1; $reads <= 60; ++$reads) {
            try {
                $run(new Budget(maxReads: $reads));

                return $reads;
            } catch (TraversalBudgetExceeded) {
            }
        }

        self::fail('No number of reads up to 60 was enough.');
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }

    private function write(string ...$tuples): void
    {
        $this->store->update([], array_map(function (string $tuple): RelationTuple {
            [$object, $rest] = explode('#', $tuple, 2);
            [$relation, $subject] = explode('@', $rest, 2);

            return new RelationTuple($this->ref($object), $relation, Subject::fromString($subject));
        }, array_values($tuples)));
    }
}
