<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Phil\DoctrineBehaviours\Contract\TreeNode;

/**
 * Keeps the paths of `TreeNode` entities: a node is placed under its parent once it is inserted, and a node whose
 * parent changed takes its new path, its branch following it — the loaded descendants with the flush, the others in
 * the database once it is over.
 *
 * The moves wait between `onFlush` and `postFlush` only: each flush starts with none, so that the moves of a flush that
 * failed — a constraint, a deadlock — are never applied by the next one. `reset()` forgets them too, for a container
 * that resets its services between requests or messages.
 */
final class TreeListener
{
    /** @var list<array{class-string, string, string}> the moves to apply to the branches: class, old path, new path */
    private array $moves = [];

    public function postPersist(PostPersistEventArgs $event): void
    {
        $node = $event->getObject();

        if (!$node instanceof TreeNode) {
            return;
        }

        $node->placeInTree(TreePaths::of($node));
        $work = $event->getObjectManager()->getUnitOfWork();
        $id = spl_object_id($node);
        $work->scheduleExtraUpdate($node, [TreeNode::PATH_FIELD => [null, $node->treePath()], TreeNode::DEPTH_FIELD => [null, $node->treeDepth()]]);
        $work->setOriginalEntityProperty($id, TreeNode::PATH_FIELD, $node->treePath());
        $work->setOriginalEntityProperty($id, TreeNode::DEPTH_FIELD, $node->treeDepth());
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        // A flush that failed after its onFlush never reached postFlush: its moves were rolled back with it.
        $this->moves = [];
        $manager = $event->getObjectManager();
        $work = $manager->getUnitOfWork();

        foreach ($work->getScheduledEntityUpdates() as $node) {
            if (!$node instanceof TreeNode) {
                continue;
            }

            $old = $node->treePath();

            if (null === $old) {
                continue;
            }

            $new = TreePaths::of($node);

            if ($old === $new) {
                continue;
            }

            $class = $manager->getClassMetadata($node::class)->rootEntityName;
            $this->moves[] = [$class, $old, $new];
            $this->moveLoaded($manager, $class, $old, $new);
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        $moves = $this->moves;
        $this->moves = [];

        foreach ($moves as [$class, $old, $new]) {
            $event->getObjectManager()->createQuery(\sprintf(
                "UPDATE %s node SET node.%2\$s = CONCAT(:new, SUBSTRING(node.%2\$s, :from)), node.%3\$s = node.%3\$s + :delta WHERE node.%2\$s LIKE :branch ESCAPE '!'",
                $class,
                TreeNode::PATH_FIELD,
                TreeNode::DEPTH_FIELD,
            ))
                ->setParameter('new', $new)
                ->setParameter('from', \strlen($old) + 1)
                ->setParameter('delta', substr_count($new, '/') - substr_count($old, '/'))
                ->setParameter('branch', TreePaths::like($old))
                ->execute();
        }
    }

    /**
     * Forgets the moves waiting for a flush to end.
     */
    public function reset(): void
    {
        $this->moves = [];
    }

    /**
     * Moves the node and its descendants already loaded, written by the flush; the database query moves the others.
     *
     * @param class-string $class
     */
    private function moveLoaded(EntityManagerInterface $manager, string $class, string $old, string $new): void
    {
        $work = $manager->getUnitOfWork();

        foreach ($work->getIdentityMap()[$class] ?? [] as $loaded) {
            if ($loaded instanceof TreeNode && str_starts_with((string) $loaded->treePath(), $old)) {
                $loaded->placeInTree($new.substr((string) $loaded->treePath(), \strlen($old)));
                $work->recomputeSingleEntityChangeSet($manager->getClassMetadata($loaded::class), $loaded);
            }
        }
    }
}
