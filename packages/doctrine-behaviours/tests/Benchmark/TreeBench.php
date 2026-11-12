<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Generator;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Folder;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\MutableBlame;
use Phil\DoctrineBehaviours\Tree;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

/**
 * Menus, folders and taxonomies read one level of a tree on every page: `children()` must cost the size of that level,
 * not of the branch under it, which `descendants()` hydrates whole. The tree has 11,111 nodes, ten children each, on
 * SQLite in memory; the identity map is cleared on every call, as on a new request.
 *
 * @Groups({"doctrine"})
 */
final class TreeBench
{
    private const int FANOUT = 10;

    private const int DEPTH = 4;

    private readonly EntityManager $manager;

    private readonly Tree $tree;

    /**
     * @var array<string, Folder>
     */
    private array $nodes = [];

    public function __construct()
    {
        $this->manager = Database::entityManager(new MockClock(new DateTimeImmutable('2026-11-04 12:00 UTC')), new MutableBlame());
        $this->tree = new Tree($this->manager);
        $connection = $this->manager->getConnection();
        $connection->beginTransaction();

        $id = 0;
        $level = [[null, '/']];

        // The paths written as the listener writes them, in one transaction: building the tree is not what is measured.
        for ($depth = 0; $depth <= self::DEPTH; ++$depth) {
            $next = [];

            foreach ($level as [$parent, $path]) {
                foreach (0 === $depth ? [0] : range(1, self::FANOUT) as $ignored) {
                    $own = $path.++$id.'/';
                    $connection->insert('folder', ['id' => $id, 'name' => 'Folder '.$id, 'parent_id' => $parent, 'tree_path' => $own, 'tree_depth' => $depth]);
                    $next[] = [$id, $own];
                }
            }

            $level = $next;
        }

        $connection->commit();

        foreach (['root' => '/1/', 'level 1' => '/1/2/', 'level 3' => '/1/2/12/112/'] as $name => $path) {
            $this->nodes[$name] = $this->manager->getRepository(Folder::class)->findOneBy(['treePath' => $path]) ?? throw new RuntimeException('No node at '.$path);
        }

        $this->manager->clear();
    }

    /**
     * @ParamProviders("nodes")
     *
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @param array{node: string} $params
     */
    public function benchChildren(array $params): void
    {
        $this->manager->clear();
        $this->tree->children($this->nodes[$params['node']]);
    }

    /**
     * @ParamProviders("nodes")
     *
     * @Revs(2)
     *
     * @Iterations(5)
     *
     * @param array{node: string} $params
     */
    public function benchDescendants(array $params): void
    {
        $this->manager->clear();
        $this->tree->descendants($this->nodes[$params['node']]);
    }

    /**
     * @return Generator<string, array{node: string}>
     */
    public function nodes(): Generator
    {
        yield 'root, 11110 below' => ['node' => 'root'];
        yield 'level 1, 1110 below' => ['node' => 'level 1'];
        yield 'level 3, 10 below' => ['node' => 'level 3'];
    }
}
