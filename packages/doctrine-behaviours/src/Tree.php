<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;
use Phil\DoctrineBehaviours\Contract\TreeNode;

/**
 * Reads a tree from the paths of its nodes: roots, children, descendants and ancestors, each in the order of the
 * paths, and the hierarchy as branches.
 */
final readonly class Tree
{
    public function __construct(
        private EntityManagerInterface $manager,
    ) {
    }

    /**
     * @template T of TreeNode
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function roots(string $class): array
    {
        return $this->matching($class, Criteria::create()->where(Criteria::expr()->eq(TreeNode::DEPTH_FIELD, 0)));
    }

    /**
     * @template T of TreeNode
     *
     * @param T $node
     *
     * @return list<T>
     */
    public function children(TreeNode $node): array
    {
        $path = $node->treePath();

        if (null === $path) {
            return [];
        }

        // One level, by its depth: the rest of the branch is never read. The prefix is checked again in PHP, since a
        // `%` or `_` of an identifier is a wildcard to LIKE.
        $found = $this->matching($node::class, Criteria::create()->where(Criteria::expr()->startsWith(TreeNode::PATH_FIELD, $path))->andWhere(Criteria::expr()->eq(TreeNode::DEPTH_FIELD, (int) $node->treeDepth() + 1)));

        return array_values(array_filter($found, fn (TreeNode $child): bool => $this->parentPath($child) === $path));
    }

    /**
     * The descendants of a node, none while it has no path.
     *
     * @template T of TreeNode
     *
     * @param T $node
     *
     * @return list<T>
     */
    public function descendants(TreeNode $node): array
    {
        $path = $node->treePath();

        if (null === $path) {
            return [];
        }

        $found = $this->matching($node::class, Criteria::create()->where(Criteria::expr()->startsWith(TreeNode::PATH_FIELD, $path))->andWhere(Criteria::expr()->gt(TreeNode::DEPTH_FIELD, (int) $node->treeDepth())));

        return array_values(array_filter($found, static fn (TreeNode $descendant): bool => str_starts_with((string) $descendant->treePath(), $path)));
    }

    /**
     * The ancestors of a node, from its root down to its parent.
     *
     * @template T of TreeNode
     *
     * @param T $node
     *
     * @return list<T>
     */
    public function ancestors(TreeNode $node): array
    {
        $ids = explode('/', trim((string) $node->treePath(), '/'));
        $paths = [];

        for ($length = 1; $length < \count($ids); ++$length) {
            $paths[] = '/'.implode('/', \array_slice($ids, 0, $length)).'/';
        }

        return $this->matching($node::class, Criteria::create()->where(Criteria::expr()->in(TreeNode::PATH_FIELD, $paths)));
    }

    /**
     * The whole tree of a class as branches, or the branch of a node; siblings in the order of their paths, or of the
     * comparison given.
     *
     * @template T of TreeNode
     *
     * @param class-string<T>|T    $from
     * @param ?callable(T, T): int $order
     *
     * @return list<Branch<T>>
     */
    public function hierarchy(string|TreeNode $from, ?callable $order = null): array
    {
        $nodes = \is_string($from) ? $this->matching($from, Criteria::create()) : [$from, ...$this->descendants($from)];
        $byParent = [];

        foreach ($nodes as $node) {
            $byParent[$this->parentPath($node)][] = $node;
        }

        return $this->branches($byParent, \is_string($from) ? '/' : $this->parentPath($from), $order);
    }

    /**
     * @template T of TreeNode
     *
     * @param array<string, list<T>> $byParent
     * @param ?callable(T, T): int   $order
     *
     * @return list<Branch<T>>
     */
    private function branches(array $byParent, string $parent, ?callable $order): array
    {
        $nodes = $byParent[$parent] ?? [];

        if (null !== $order) {
            usort($nodes, $order);
        }

        return array_map(fn (TreeNode $node): Branch => new Branch($node, $this->branches($byParent, (string) $node->treePath(), $order)), $nodes);
    }

    private function parentPath(TreeNode $node): string
    {
        return substr((string) $node->treePath(), 0, -\strlen($node->treeId()) - 1);
    }

    /**
     * @template T of TreeNode
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function matching(string $class, Criteria $criteria): array
    {
        return $this->manager->getRepository($class)->matching($criteria->orderBy([TreeNode::PATH_FIELD => Order::Ascending]))->getValues();
    }
}
