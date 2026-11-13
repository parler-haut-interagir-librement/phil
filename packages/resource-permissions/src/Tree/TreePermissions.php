<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tree;

use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Exception\InvalidRelation;
use Phil\ResourcePermissions\Exception\InvalidTree;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Phil\ResourcePermissions\RelationName;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;

/**
 * Permissions per resource, inherited down a tree: each node may hold, per action, entries that allow or deny it to
 * a role or to everyone else, and the most specific entry wins.
 *
 * From the resource up to the root, the first node with an entry for one of the member's roles or for everyone else
 * decides. On that node, the member's roles come before everyone else, and a denial wins over an allowance at the same
 * level, whichever role carries it. A tree without any such entry is undecided, and the application's default applies.
 *
 * Entries and the tree's links are tuples of the store, the same ones the relation model reads: an entry is
 * `node#allow.<action>@role:editor`, `node#deny.<action>@everyone:*`; a link is `node#parent@folder:7`, so that a
 * rewrite `fromRelated('parent', …)` follows the same tree.
 */
final readonly class TreePermissions
{
    public const int MAX_ROLES = 64;

    public const int MAX_ACTION_LENGTH = 58;

    /**
     * @param positive-int $maxDepth the nodes walked from a resource to its root, at most
     */
    public function __construct(
        private TupleStore $store,
        private string $parentRelation = 'parent',
        private int $maxDepth = 64,
    ) {
        RelationName::assert($parentRelation);
    }

    /**
     * Places `$node` under `$parent`, or at the root when `$parent` is null, replacing its parent.
     */
    public function move(ResourceReference $node, ?ResourceReference $parent): void
    {
        $this->store->update(
            $this->store->subjects($node, $this->parentRelation, 10),
            $parent instanceof ResourceReference ? [new RelationTuple($node, $this->parentRelation, Subject::of($parent))] : [],
        );
    }

    public function allow(ResourceReference $node, string $action, Grantee $grantee): void
    {
        $this->set($node, $action, $grantee, Decision::Allow);
    }

    public function deny(ResourceReference $node, string $action, Grantee $grantee): void
    {
        $this->set($node, $action, $grantee, Decision::Deny);
    }

    /**
     * Removes the entry of `$grantee` for `$action` on `$node`, so that the node inherits again.
     */
    public function clear(ResourceReference $node, string $action, Grantee $grantee): void
    {
        $this->store->update([
            new RelationTuple($node, $this->relation(Decision::Allow, $action), $grantee->subject()),
            new RelationTuple($node, $this->relation(Decision::Deny, $action), $grantee->subject()),
        ], []);
    }

    /**
     * The entries of `$node` for `$action`, denials first, at most a hundred of each.
     *
     * @return list<TreeEntry>
     */
    public function entries(ResourceReference $node, string $action): array
    {
        $entries = [];

        foreach ([Decision::Deny, Decision::Allow] as $decision) {
            foreach ($this->store->subjects($node, $this->relation($decision, $action), 100) as $tuple) {
                $entries[] = new TreeEntry(Grantee::fromSubject($tuple->subject), $decision);
            }
        }

        return $entries;
    }

    /**
     * What the tree says of `$action` on `$node` for a member holding `$roles`.
     *
     * @param list<ResourceReference> $roles
     *
     * @throws InvalidTree             when a node has two parents or is its own ancestor
     * @throws TraversalBudgetExceeded when the tree is deeper than the maximum depth
     */
    public function decide(ResourceReference $node, string $action, array $roles): TreeDecision
    {
        if (\count($roles) > self::MAX_ROLES) {
            throw InvalidRelation::because(\sprintf('A decision reads at most %d roles.', self::MAX_ROLES));
        }

        $relations = [$this->relation(Decision::Allow, $action), $this->relation(Decision::Deny, $action)];
        $subjects = [...array_map(Subject::of(...), $roles), Grantee::everyoneElse()->subject()];
        $seen = [];

        for ($depth = 0; $node instanceof ResourceReference; ++$depth) {
            if ($depth >= $this->maxDepth) {
                throw TraversalBudgetExceeded::depth($this->maxDepth);
            }

            if (isset($seen[$node->toString()])) {
                throw InvalidTree::cycle($node->toString());
            }

            $seen[$node->toString()] = true;
            $decision = $this->decideOn($node, $this->store->matching($node, $relations, $subjects));

            if ($decision instanceof TreeDecision) {
                return $decision;
            }

            $node = $this->parent($node);
        }

        return new TreeDecision(Decision::Undecided);
    }

    /**
     * The relation of an entry, `allow.<action>` or `deny.<action>`, under the rule of a relation's name, which keeps an
     * action to `MAX_ACTION_LENGTH` bytes.
     */
    private function relation(Decision $decision, string $action): string
    {
        return RelationName::assert($decision->value.'.'.RelationName::assert($action));
    }

    /**
     * @param list<RelationTuple> $tuples
     */
    private function decideOn(ResourceReference $node, array $tuples): ?TreeDecision
    {
        $forRoles = array_values(array_filter($tuples, static fn (RelationTuple $tuple): bool => !$tuple->subject->isEvery()));
        $entries = [] === $forRoles ? $tuples : $forRoles;

        if ([] === $entries) {
            return null;
        }

        $denials = array_values(array_filter($entries, static fn (RelationTuple $tuple): bool => str_starts_with($tuple->relation, Decision::Deny->value.'.')));
        $deciding = [] === $denials ? $entries : $denials;

        return new TreeDecision(
            [] === $denials ? Decision::Allow : Decision::Deny,
            $node,
            array_map(static fn (RelationTuple $tuple): Grantee => Grantee::fromSubject($tuple->subject), $deciding),
        );
    }

    private function parent(ResourceReference $node): ?ResourceReference
    {
        $parents = $this->store->subjects($node, $this->parentRelation, 2);

        if (\count($parents) > 1) {
            throw InvalidTree::twoParents($node->toString());
        }

        return [] === $parents ? null : $parents[0]->subject->reference;
    }

    private function set(ResourceReference $node, string $action, Grantee $grantee, Decision $decision): void
    {
        $opposite = Decision::Allow === $decision ? Decision::Deny : Decision::Allow;

        $this->store->update(
            [new RelationTuple($node, $this->relation($opposite, $action), $grantee->subject())],
            [new RelationTuple($node, $this->relation($decision, $action), $grantee->subject())],
        );
    }
}
