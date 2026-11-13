<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit\Tree;

use Override;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\InvalidTree;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Phil\ResourcePermissions\Tree\Decision;
use Phil\ResourcePermissions\Tree\Grantee;
use Phil\ResourcePermissions\Tree\TreeDecision;
use Phil\ResourcePermissions\Tree\TreeEntry;
use Phil\ResourcePermissions\Tree\TreePermissions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(TreePermissions::class)]
#[CoversClass(Grantee::class)]
#[CoversClass(TreeDecision::class)]
#[CoversClass(TreeEntry::class)]
#[CoversClass(InvalidTree::class)]
final class TreePermissionsTest extends TestCase
{
    private InMemoryTupleStore $store;

    private TreePermissions $tree;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryTupleStore(new MockClock());
        $this->tree = new TreePermissions($this->store);
        // wiki ─ guides ─ page
        $this->tree->move($this->ref('book:guides'), $this->ref('book:wiki'));
        $this->tree->move($this->ref('page:1'), $this->ref('book:guides'));
    }

    public function testAnUndecidedTree(): void
    {
        $decision = $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')]);

        self::assertSame(Decision::Undecided, $decision->decision);
        self::assertNull($decision->node);
        self::assertSame([], $decision->grantees);
        self::assertFalse($decision->isAllowed());
    }

    public function testAnEntryIsInherited(): void
    {
        $this->tree->allow($this->ref('book:wiki'), 'view', Grantee::role($this->ref('role:editor')));

        $decision = $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')]);

        self::assertSame(Decision::Allow, $decision->decision);
        self::assertTrue($decision->isAllowed());
        self::assertSame('book:wiki', $decision->node?->toString());
        self::assertSame('role:editor', $decision->grantees[0]->role?->toString());
        self::assertSame(Decision::Undecided, $this->tree->decide($this->ref('page:1'), 'edit', [$this->ref('role:editor')])->decision);
        self::assertSame(Decision::Undecided, $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:reader')])->decision);
    }

    public function testTheMostSpecificNodeWins(): void
    {
        $this->tree->allow($this->ref('book:wiki'), 'view', Grantee::role($this->ref('role:editor')));
        $this->tree->deny($this->ref('book:guides'), 'view', Grantee::everyoneElse());

        self::assertSame(Decision::Deny, $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')])->decision);
        self::assertSame('book:guides', $this->tree->decide($this->ref('page:1'), 'view', [])->node?->toString());
        self::assertTrue($this->tree->decide($this->ref('page:1'), 'view', [])->grantees[0]->isEveryoneElse());
        self::assertSame(Decision::Allow, $this->tree->decide($this->ref('book:wiki'), 'view', [$this->ref('role:editor')])->decision);
    }

    public function testARoleComesBeforeEveryoneElseOnTheSameNode(): void
    {
        $this->tree->deny($this->ref('page:1'), 'view', Grantee::everyoneElse());
        $this->tree->allow($this->ref('page:1'), 'view', Grantee::role($this->ref('role:editor')));

        self::assertSame(Decision::Allow, $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')])->decision);
        self::assertSame(Decision::Deny, $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:reader')])->decision);
    }

    public function testEveryoneElseAllowed(): void
    {
        $this->tree->allow($this->ref('book:guides'), 'view', Grantee::everyoneElse());

        $decision = $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:reader')]);
        self::assertSame(Decision::Allow, $decision->decision);
        self::assertSame([true], array_map(static fn (Grantee $grantee): bool => $grantee->isEveryoneElse(), $decision->grantees));
    }

    public function testADenialWinsAtTheSameLevel(): void
    {
        $this->tree->allow($this->ref('page:1'), 'view', Grantee::role($this->ref('role:editor')));
        $this->tree->deny($this->ref('page:1'), 'view', Grantee::role($this->ref('role:suspended')));

        $decision = $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor'), $this->ref('role:suspended')]);

        self::assertSame(Decision::Deny, $decision->decision);
        self::assertSame(['role:suspended'], array_map(static fn (Grantee $grantee): ?string => $grantee->role?->toString(), $decision->grantees));
        self::assertSame(Decision::Allow, $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')])->decision);
    }

    public function testAllowAndDenyReplaceEachOtherAndClearInherits(): void
    {
        $editor = Grantee::role($this->ref('role:editor'));
        $this->tree->allow($this->ref('book:wiki'), 'view', $editor);
        $this->tree->deny($this->ref('page:1'), 'view', $editor);
        self::assertSame(Decision::Deny, $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')])->decision);

        $this->tree->allow($this->ref('page:1'), 'view', $editor);
        self::assertSame('page:1', $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')])->node?->toString());
        self::assertCount(1, $this->tree->entries($this->ref('page:1'), 'view'));

        $this->tree->deny($this->ref('page:1'), 'view', $editor);
        self::assertCount(1, $this->tree->entries($this->ref('page:1'), 'view'));

        $this->tree->clear($this->ref('page:1'), 'view', $editor);
        self::assertSame([], $this->tree->entries($this->ref('page:1'), 'view'));
        self::assertSame('book:wiki', $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')])->node?->toString());
    }

    public function testEntries(): void
    {
        $this->tree->allow($this->ref('page:1'), 'view', Grantee::role($this->ref('role:editor')));
        $this->tree->deny($this->ref('page:1'), 'view', Grantee::everyoneElse());
        $this->tree->allow($this->ref('page:1'), 'edit', Grantee::role($this->ref('role:editor')));

        $entries = $this->tree->entries($this->ref('page:1'), 'view');

        self::assertSame([Decision::Deny, Decision::Allow], array_map(static fn (TreeEntry $entry): Decision => $entry->decision, $entries));
        self::assertTrue($entries[0]->grantee->isEveryoneElse());
        self::assertSame('role:editor', $entries[1]->grantee->role?->toString());
        self::assertFalse($entries[1]->grantee->isEveryoneElse());
    }

    public function testEntriesAreTuplesOfTheStore(): void
    {
        $this->tree->allow($this->ref('page:1'), 'view', Grantee::role($this->ref('role:editor')));
        $this->tree->deny($this->ref('page:1'), 'view', Grantee::everyoneElse());

        self::assertCount(1, $this->store->matching($this->ref('page:1'), ['allow.view'], [Subject::fromString('role:editor')]));
        self::assertCount(1, $this->store->matching($this->ref('page:1'), ['deny.view'], [Subject::every('everyone')]));
        self::assertCount(1, $this->store->matching($this->ref('page:1'), ['parent'], [Subject::fromString('book:guides')]));
    }

    public function testMoveReplacesTheParent(): void
    {
        $this->tree->allow($this->ref('book:wiki'), 'view', Grantee::role($this->ref('role:editor')));
        $this->tree->move($this->ref('page:1'), $this->ref('book:other'));
        self::assertSame(Decision::Undecided, $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')])->decision);

        $this->tree->move($this->ref('page:1'), $this->ref('book:guides'));
        $this->tree->move($this->ref('book:guides'), null);
        self::assertSame(Decision::Undecided, $this->tree->decide($this->ref('page:1'), 'view', [$this->ref('role:editor')])->decision);
        self::assertSame([], $this->store->subjects($this->ref('book:guides'), 'parent', 10));
    }

    public function testACycleIsRefused(): void
    {
        $this->store->update([], [new RelationTuple($this->ref('book:wiki'), 'parent', Subject::fromString('page:1'))]);

        $this->expectException(InvalidTree::class);
        $this->expectExceptionMessageIsOrContains('The node "page:1" is its own ancestor.');

        $this->tree->decide($this->ref('page:1'), 'view', []);
    }

    public function testTwoParentsAreRefused(): void
    {
        $this->store->update([], [new RelationTuple($this->ref('page:1'), 'parent', Subject::fromString('book:other'))]);

        $this->expectException(InvalidTree::class);
        $this->expectExceptionMessageIsOrContains('The node "page:1" has more than one parent.');

        $this->tree->decide($this->ref('page:1'), 'view', []);
    }

    public function testTheDepthIsBounded(): void
    {
        $tree = new TreePermissions($this->store, 'parent', 3);
        self::assertSame(Decision::Undecided, $tree->decide($this->ref('page:1'), 'view', [])->decision);

        $this->expectException(TraversalBudgetExceeded::class);
        $this->expectExceptionMessageIsOrContains('deeper than 2 steps');

        new TreePermissions($this->store, 'parent', 2)->decide($this->ref('page:1'), 'view', []);
    }

    public function testAnotherParentRelation(): void
    {
        $tree = new TreePermissions($this->store, 'folder');
        $tree->move($this->ref('doc:1'), $this->ref('folder:f'));
        $tree->allow($this->ref('folder:f'), 'view', Grantee::everyoneElse());

        self::assertSame(Decision::Allow, $tree->decide($this->ref('doc:1'), 'view', [])->decision);
        self::assertSame(Decision::Undecided, $this->tree->decide($this->ref('doc:1'), 'view', [])->decision);
    }

    public function testTheRolesAreBounded(): void
    {
        $roles = array_map(static fn (int $i): ResourceReference => ResourceReference::of('role', 'r'.$i), range(1, TreePermissions::MAX_ROLES));
        self::assertSame(Decision::Undecided, $this->tree->decide($this->ref('page:1'), 'view', $roles)->decision);

        $this->expectException(InvalidRelation::class);
        $this->expectExceptionMessageIsOrContains('A decision reads at most 64 roles.');

        $this->tree->decide($this->ref('page:1'), 'view', [...$roles, ResourceReference::of('role', 'more')]);
    }

    public function testActionsFollowTheRules(): void
    {
        $this->tree->allow($this->ref('page:1'), 'v'.str_repeat('x', TreePermissions::MAX_ACTION_LENGTH - 1), Grantee::everyoneElse());

        foreach (['v'.str_repeat('x', TreePermissions::MAX_ACTION_LENGTH), 'View', '1'] as $action) {
            try {
                $this->tree->allow($this->ref('page:1'), $action, Grantee::everyoneElse());
                self::fail(\sprintf('"%s" should be refused.', $action));
            } catch (InvalidRelation) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAParentRelationFollowsTheRules(): void
    {
        $this->expectException(InvalidRelation::class);

        new TreePermissions($this->store, 'Parent');
    }

    public function testGrantees(): void
    {
        self::assertTrue(Grantee::fromSubject(Subject::every('everyone'))->isEveryoneElse());
        self::assertSame('role:*x', Grantee::fromSubject(Subject::fromString('role:*x'))->role?->toString());
        self::assertSame('everyone:*', Grantee::everyoneElse()->subject()->toString());
        self::assertSame('role:a', Grantee::role($this->ref('role:a'))->subject()->toString());

        $this->expectException(InvalidRelation::class);

        Grantee::fromSubject(Subject::every('role'));
    }

    private function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
