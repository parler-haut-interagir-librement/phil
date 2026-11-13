<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit\Tree;

use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Phil\ResourcePermissions\Tree\Decision;
use Phil\ResourcePermissions\Tree\Grantee;
use Phil\ResourcePermissions\Tree\TreePermissions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The reach of a tree's limits, and what clearing and listing entries touch.
 */
#[CoversClass(TreePermissions::class)]
final class TreePermissionsLimitsTest extends TestCase
{
    public function testSixtyFourNodesAreWalkedByDefaultAndNoMore(): void
    {
        $store = new InMemoryTupleStore(new MockClock());
        $tree = new TreePermissions($store);

        for ($i = 1; $i < 65; ++$i) {
            $tree->move($this->ref('node:'.$i), $this->ref('node:'.($i + 1)));
        }

        self::assertSame(Decision::Undecided, $tree->decide($this->ref('node:2'), 'view', [])->decision);

        $this->expectException(TraversalBudgetExceeded::class);

        $tree->decide($this->ref('node:1'), 'view', []);
    }

    public function testMovingReplacesAtMostTenParentsRead(): void
    {
        $store = new InMemoryTupleStore(new MockClock());
        $tree = new TreePermissions($store);
        $node = $this->ref('page:1');
        $store->update([], array_map(static fn (int $i): RelationTuple => new RelationTuple($node, 'parent', Subject::of(ResourceReference::of('book', (string) $i))), range(100, 110)));

        $tree->move($node, $this->ref('book:new'));

        self::assertCount(2, $store->subjects($node, 'parent', 100));
    }

    public function testClearingRemovesAnAllowanceToo(): void
    {
        $tree = new TreePermissions(new InMemoryTupleStore(new MockClock()));
        $role = Grantee::role($this->ref('role:editor'));
        $tree->allow($this->ref('page:1'), 'view', $role);

        $tree->clear($this->ref('page:1'), 'view', $role);

        self::assertSame([], $tree->entries($this->ref('page:1'), 'view'));
        self::assertSame(Decision::Undecided, $tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')])->decision);
    }

    public function testEntriesAreListedUpToAHundredOfEachKind(): void
    {
        $tree = new TreePermissions(new InMemoryTupleStore(new MockClock()));

        for ($i = 100; $i < 201; ++$i) {
            $tree->allow($this->ref('page:1'), 'view', Grantee::role($this->ref('role:r'.$i)));
        }

        self::assertCount(100, $tree->entries($this->ref('page:1'), 'view'));
    }

    public function testTheGranteesOfADecisionAreAList(): void
    {
        $tree = new TreePermissions(new InMemoryTupleStore(new MockClock()));
        $tree->allow($this->ref('page:1'), 'view', Grantee::everyoneElse());
        $tree->allow($this->ref('page:1'), 'view', Grantee::role($this->ref('role:editor')));

        $decision = $tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')]);

        self::assertSame(Decision::Allow, $decision->decision);
        self::assertCount(1, $decision->grantees);
        self::assertTrue(array_is_list($decision->grantees));
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
